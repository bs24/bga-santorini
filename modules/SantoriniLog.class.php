<?php

/*
 * SantoriniLog: a class that allows to log some actions
 *   and then fetch these actions latter (useful for powers or rollback)
 *   also responsible for managing game statistics
 */
class SantoriniLog extends APP_GameClass
{
  public $game;
  public function __construct($game)
  {
    $this->game = $game;
  }

  /////////////////////////////////
  /////////////////////////////////
  ///////  Game Statistics  ///////
  /////////////////////////////////
  /////////////////////////////////

  /*
   * initStats: initialize statistics to 0 at start of game
   */
  public function initStats($players)
  {
    $this->game->initStat('table', 'move', 0);
    $this->game->initStat('table', 'buildBlock', 0);
    $this->game->initStat('table', 'buildDome', 0);
    $this->game->initStat('table', 'buildTower', 0);

    foreach ($players as $pId => $player) {
      $this->game->initStat('player', 'playerPower', 0, $pId);
      $this->game->initStat('player', 'usePower', 0, $pId);
      $this->game->initStat('player', 'move', 0, $pId);
      $this->game->initStat('player', 'moveUp', 0, $pId);
      $this->game->initStat('player', 'moveDown', 0, $pId);
      $this->game->initStat('player', 'buildBlock', 0, $pId);
      $this->game->initStat('player', 'buildDome', 0, $pId);
      $this->game->initStat('player', 'restartTurn', 0, $pId);
    }
  }

  /*
   * gameEndStats: compute end-of-game statistics
   */
  public function gameEndStats()
  {
    $this->game->setStat($this->game->board->getCompleteTowerCount(), 'buildTower');
  }

  /*
   * incrementStats: adjust individual game statistics
   *   - array $stats: format is array of [ playerId, name, value ].
   *     example: [ ['table', 'move'], [23647584, 'move'], ... ]
   *       - playerId: the player ID for a player state, or 'table' for a table stat
   *       - name: the state name, such as 'move' or 'usePower'
   *       - value (optional): amount to add, defaults to 1
   *   - boolean $subtract: true if the values should be decremented
   */
  public function incrementStats($stats, $subtract = false)
  {
    foreach ($stats as $stat) {
      if (!is_array($stat)) {
        throw new BgaVisibleSystemException("incrementStats: Not an array");
      }

      $pId = $stat[0];
      if ($pId == 'table' || empty($pId)) {
        $pId = null;
      }

      $name = $stat[1];
      if (empty($name)) {
        throw new BgaVisibleSystemException("incrementStats: Missing name");
      }

      $value = 1;
      if (count($stat) > 2) {
        $value = $stat[2];
      }
      if ($subtract) {
        $value = $value * -1;
      }

      $this->game->incStat($value, $name, $pId);

      /*
      // DEBUG: Print stat changes
      $args = [
        'player_name' => 'Table',
        'name' => $name,
        'value' => $this->game->getStat($name, $pId),
      ];
      if ($pId) {
        $args['player_name'] = $this->game->playerManager->getPlayer($pId)->getName();
      }
      $this->game->notifyAllPlayers('message', '[Statistics] ${player_name} ${name}=${value}', $args);
      */
    }
  }

  ////////////////////////////////
  ////////////////////////////////
  //////////   Adders   //////////
  ////////////////////////////////
  ////////////////////////////////

  /*
   * insert: add a new log entry
   * params:
   *   - $playerId: the player who is making the action
   *   - $pieceId : the piece whose is making the action
   *   - string $action : the name of the action
   *   - array $stats: game statistics simple array (e.g. [ ['table', 'move'], ['287392', 'usePower'], ... ]
   *   - array $args : action arguments (eg space)
   */
  public function insert($playerId, $pieceId, $action, $stats = [], $args = [])
  {
    $playerId = $playerId == -1 ? $this->game->getActivePlayerId() : $playerId;
    $moveId = self::getUniqueValueFromDB("SELECT global_value FROM global WHERE global_id = 3");

    if ($action == 'move') {
      $stats[] = ['table', 'move'];
      $stats[] = [$playerId, 'move'];
      if ($args['to']['z'] > $args['from']['z']) {
        $stats[] = [$playerId, 'moveUp'];
      } else if ($args['to']['z'] < $args['from']['z']) {
        $stats[] = [$playerId, 'moveDown'];
      }
    } else if ($action == 'build') {
      $statName = $args['to']['arg'] == 3 ? 'buildDome' : 'buildBlock';
      $stats[] = ['table', $statName];
      $stats[] = [$playerId, $statName];
    }
    if (!empty($stats)) {
      $this->incrementStats($stats);
      $args['stats'] = $stats;
    }

    $actionArgs = json_encode($args);
    self::DbQuery("INSERT INTO log (move_id, player_id, piece_id, action, action_arg) VALUES ($moveId, $playerId, $pieceId, '$action', '$actionArgs')");
  }


  /*
   * starTurn: TODO
   */
  public function startTurn()
  {
    $this->insert(-1, 0, 'startTurn');
  }

  /*
   * addWork: add a new work entry to log
   */
  private function addWork($piece, $to, $action, $stats = [])
  {
    $args = [
      'from' => SantoriniBoard::getCoords($piece),
      'to'   => $to,
    ];
    $this->insert(-1, $piece['id'], $action, $stats, $args);
  }

  /*
   * addMove: add a new move entry to log
   */
  public function addMove($piece, $space, $stats = [])
  {
    $this->addWork($piece, $space, 'move', $stats);
  }

  /*
   * addBuild: add a new build entry to log
   */
  public function addBuild($piece, $space, $stats = [])
  {
    $this->addWork($piece, $space, 'build', $stats);
  }

  /*
   * addForce: add a new forced move entry to log (eg. Appolo or Minotaur)
   */
  public function addForce($piece, $space, $stats = [])
  {
    $this->addWork($piece, $space, 'force', $stats);
  }

  /*
   * addWhirlpoolMove: add a new whirlpool move entry to log. $piece contains the space below $space
   */
  public function addWhirlpoolMove($piece, $space, $stats = [])
  {
    $this->addWork($piece, $space, 'whirlpoolMove', $stats);
  }

  /*
  /*
   * addRemoval: add a piece removal entry to log (eg. Bia or Ares)
   * NOTE: call this BEFORE updating board, since it saves the current location
   */
  public function addRemoval($piece, $stats = [])
  {
    $args = [
      'location' => $piece['location'],
      'from' => SantoriniBoard::getCoords($piece),
    ];
    $this->insert(-1, $piece['id'], 'removal', $stats, $args);
  }

  /*
   * addPlaceWorker: add a new place worker entry to log (e.g., Jason)
   */
  public function addPlaceWorker($worker, $power, $location = 'hand')
  {
    $args = [
      'power_id' => $power->getId(),
      'player_id' => $power->getPlayerId(),
      'location' => $location,
      'to' => SantoriniBoard::getCoords($worker),
    ];
    $this->insert(-1, $worker['id'], 'placeWorker', [], $args);
  }

  /*
   * addPlaceToken: add a new place token entry to log (e.g., Europa)
   */
  public function addPlaceToken($token, $power, $stats, $location = 'hand')
  {
    $args = [
      'power_id' => $power->getId(),
      'player_id' => $power->getPlayerId(),
      'location' => $location,
      'to' => SantoriniBoard::getCoords($token),
    ];
    $this->insert(-1, $token['id'], 'placeToken', $stats, $args);
  }

  /*
   * addMoveToken: add a new move token entry to log (e.g., Europa)
   */
  public function addMoveToken($token, $space, $power, $stats = [])
  {
    $args = [
      'power_id' => $power->getId(),
      'player_id' => $power->getPlayerId(),
      'from' => SantoriniBoard::getCoords($token),
      'to'   => $space,
    ];
    $this->insert(-1, $token['id'], 'moveToken', $stats, $args);
  }


  /*
   * addAction: add a new action to log
   */
  public function addAction($action, $stats = [], $args = [], $playerId = -1)
  {
    $this->insert($playerId, 0, $action, $stats, $args);
  }


  /////////////////////////////////
  /////////////////////////////////
  //////////   Getters   //////////
  /////////////////////////////////
  /////////////////////////////////

  /* getRoundClause
   * mixed $offset can be:
   *  - 'all', to get all rows for the entire game
   *  - a number, to get rows since this player's startTurn
   *  - an action name, to get rows since anyone's instance of this action
   */
  private function getRoundClause($pId, $offset = 0, $additionalTurns = false)
  {
    $offset = $offset ?: 0;
    if ($offset === 'all') {
      return "";
    }

    if (is_numeric($offset)) {
      // Offset > 0 used by Circe
      $actions = ['startTurn'];
      if (!$additionalTurns) {
        $actions[] = 'additionalTurn';
      }
      return " AND log_id > (SELECT log_id FROM log WHERE player_id = $pId AND action IN ('" . implode("', '", $actions) . "') ORDER BY log_id DESC LIMIT 1 OFFSET $offset)";
    } else {
      // Offset as action name used by Gaea
      return " AND log_id > (SELECT log_id FROM log WHERE action = '$offset' ORDER BY log_id DESC LIMIT 1)";
    }
  }

  /*
 * getLastWorks: fetch last works of player of current round
 * params:
 *    - string $action : type of work we want to fetch (move/build)
 *    - optionnal int $pId : the player we are interested in, default is active player
 *    - optional int $limit : the number of works we want to fetched (order by most recent first), default is no-limit (-1)
 *    - optional bool $additionalTurns : whether to include works of prior additional turns during the current round (e.g., Dionysus, Tyche)
 */
  public function getLastWorks($actions, $pId = null, $limit = -1, $additionalTurns = false)
  {
    $pId = $pId ?: $this->game->getActivePlayerId();
    $limitClause = ($limit == -1) ? '' : "LIMIT $limit";
    $actionsNames = "'" . (is_array($actions) ? implode("','", $actions) : $actions) . "'";

    $works = self::getObjectListFromDb("SELECT * FROM log WHERE `action` IN ($actionsNames) AND `player_id` = '$pId' " . $this->getRoundClause($pId, 0, $additionalTurns) . " ORDER BY log_id DESC " . $limitClause);

    return array_map(function ($work) {
      $args = json_decode($work['action_arg'], true);
      return [
        'action' => $work['action'],
        'moveId' => $work['move_id'],
        'pieceId' => $work['piece_id'],
        'from' => $args['from'],
        'to' => $args['to'],
      ];
    }, $works);
  }

  /*
   * getLastWork: fetch the last move/build of player of current round if it exists, null otherwise
   */
  public function getLastWork($pId = null, $additionalTurns = false)
  {
    $works = $this->getLastWorks(['move', 'build'], $pId, 1);
    return (count($works) == 1) ? $works[0] : null;
  }

  /*
   * getLastWork: fetch the last move/build/whirlpool teleport of player of current round if it exists, null otherwise
   */
  public function getLastWinableWork($pId = null, $additionalTurns = false)
  {
    $works = $this->getLastWorks(['move', 'build', 'whirlpoolMove'], $pId, 1);
    if (count($works) == 0) {
      return null;
    }
    $work = $works[0];
    if ($work['action'] == 'whirlpoolMove') {
      $work['action'] = 'move'; // whirlpoolMove acts as a move regarding winning conditions
    }
    return $work;
  }


  /*
   * getLastMoves: fetch last moves of player of current round
   */
  public function getLastMoves($pId = null, $limit = -1, $additionalTurns = false)
  {
    return $this->getLastWorks('move', $pId, $limit, $additionalTurns);
  }

  /*
   * getLastMove: fetch the last move of player of current round if it exists, null otherwise
   */
  public function getLastMove($pId = null, $additionalTurns = false)
  {
    $moves = $this->getLastMoves($pId, 1, $additionalTurns);
    return (count($moves) == 1) ? $moves[0] : null;
  }

  /*
  * getLastMoveOfWorker: fetch the last move of worker of current round if it exists, null otherwise
  */
  public function getLastMoveOfWorker($workerId)
  {
    $pId = $this->game->getActivePlayerId();
    $move = self::getObjectFromDb("SELECT * FROM log WHERE `action` = 'move' AND `piece_id` = '$workerId' AND `player_id` = '$pId' " . $this->getRoundClause($pId, 0, false) . " ORDER BY log_id DESC LIMIT 1");
    if ($move == null) {
      return null;
    }
    return json_decode($move['action_arg'], true);
  }



  /*
   * getLastBuilds: fetch last builds of player of current round
   */
  public function getLastBuilds($pId = null, $limit = -1, $additionalTurns = false)
  {
    return $this->getLastWorks('build', $pId, $limit, $additionalTurns);
  }

  /*
   * getLastBuild: fetch the last build of player of current round if it exists, null otherwise
   */
  public function getLastBuild($pId = null, $additionalTurns = false)
  {
    $builds = $this->getLastBuilds($pId, 1, $additionalTurns);
    return (count($builds) == 1) ? $builds[0] : null;
  }


  /*
   * getLastActions : get works and actions of player
   */
  public function getLastActions($actions, $pId = null, $offset = null, $additionalTurns = false)
  {
    $pId = $pId ?: $this->game->getActivePlayerId();
    $actionsNames = "'" . implode("','", $actions) . "'";
    return self::getObjectListFromDb("SELECT * FROM log WHERE `action` IN ($actionsNames) AND `player_id` = '$pId' " . $this->getRoundClause($pId, $offset, $additionalTurns) . " ORDER BY log_id DESC");
  }

  public function getLastAction($action, $pId = null, $offset = null, $additionalTurns = false)
  {
    $actions = $this->getLastActions([$action], $pId, $offset, $additionalTurns);
    return count($actions) > 0 ? json_decode($actions[0]['action_arg'], true) : null;
  }


  public function getActions($actions, $pId = null)
  {
    $pId = $pId ?: $this->game->getActivePlayerId();
    $actionsNames = "'" . implode("','", $actions) . "'";

    return self::getObjectListFromDb("SELECT * FROM log WHERE `action` IN ($actionsNames) AND `player_id` = '$pId' ORDER BY log_id DESC");
  }

  public function getAllActions($actions)
  {
    $actionsNames = "'" . implode("','", $actions) . "'";

    return self::getObjectListFromDb("SELECT * FROM log WHERE `action` IN ($actionsNames) ORDER BY log_id DESC");
  }


  public function isAdditionalTurn($powerId = null)
  {
    $action = self::getObjectFromDb("SELECT * FROM log WHERE `action` IN ('startTurn', 'additionalTurn') ORDER BY log_id DESC LIMIT 1");
    if ($action != null && $action['action'] == 'additionalTurn' && $action['player_id'] == $this->game->getActivePlayerId()) {
      $args = json_decode($action['action_arg'], true);
      return $powerId == null || $powerId == $args['power_id'];
    }
    return false;
  }

  ////////////////////////////////
  ////////////////////////////////
  //////////   Cancel   //////////
  ////////////////////////////////
  ////////////////////////////////

  public function logsForCancelTurn($ignore = ['startTurn'])
  {
    $pId = $this->game->getActivePlayerId();
    $logs = self::getObjectListFromDb("SELECT * FROM log WHERE action NOT IN ('" . implode("', '", $ignore) . "') " . $this->getRoundClause($pId, 0, false) . " ORDER BY log_id DESC");
    return $logs;
  }

  public function canCancelTurn()
  {
    // Gaea: Block restart turn during power (could be during an opponent's turn)
    if ($this->game->powerManager->hasPower(GAEA) &&  $this->game->gamestate->state()['name'] == 'playerUsePower') {
      return false;
    }
    return !empty($this->logsForCancelTurn(['startTurn', 'morpheusStart', 'blockedWorker', 'forcedWorkers']));
  }

  // stop at $logIdBreak (for Hecate)
  public function cancelTurn($logIdBreak = null)
  {
    $pId = $this->game->getActivePlayerId();
    $logs = $this->logsForCancelTurn();

    $ids = [];
    $moveIds = [];
    foreach ($logs as $log) {
      $args = json_decode($log['action_arg'], true);

      if ($log['action'] == 'move' || $log['action'] == 'force' || $log['action'] == 'moveToken') {
        // Move/force : go back to initial position
        self::DbQuery("UPDATE piece SET x = {$args['from']['x']}, y = {$args['from']['y']}, z = {$args['from']['z']} WHERE id = {$log['piece_id']}");
      } else if ($log['action'] == 'build') {
        // Build : remove the piece
        self::DbQuery("DELETE FROM piece WHERE location = 'board' AND x = {$args['to']['x']} AND y = {$args['to']['y']} AND z = {$args['to']['z']}");
        $this->game->board->adjustSecretTokens($args['to'], false);
      } else if ($log['action'] == 'removal') {
        // Removal : put the piece back
        $piece = $this->game->board->getPiece($log['piece_id']);
        $location = $args['location'] ?? 'board';
        if (isset($args['from'])) {
          self::DbQuery("UPDATE piece SET location = '$location', x = {$args['from']['x']}, y = {$args['from']['y']}, z = {$args['from']['z']} WHERE id = {$log['piece_id']}");
        } else {
          // Old version 210315-0017
          self::DbQuery("UPDATE piece SET location = '$location' WHERE id = {$log['piece_id']}");
        }
        $piece = $this->game->board->getPiece($log['piece_id']);
        $this->game->board->adjustSecretTokens($piece, false);
      } else if ($log['action'] == 'powerRemoved' && $args['reason'] == 'hero') {
        // Discard hero power : put the power back
        $power = $this->game->powerManager->getPower($args['power_id'], $args['player_id']);
        $this->game->powerManager->addPower($power, 'hero');
      } else if ($log['action'] == 'placeWorker' || $log['action'] == 'placeToken') {
        // Place worker : remove the worker
        self::DbQuery("UPDATE piece SET x = null, y = null, z = null, location = '" . $args['location'] . "' WHERE id = {$log['piece_id']}");
      }

      if (array_key_exists('stats', $args)) {
        // Undo statistics
        $this->incrementStats($args['stats'], true);
      }

      $ids[] = intval($log['log_id']);
      $moveIds[] = intval($log['move_id']);

      // Hecate: stop cancelling at this point
      if ($logIdBreak == $log['log_id']) {
        break;
      }
    }

    // Remove the logs
    self::DbQuery("DELETE FROM log WHERE `player_id` = '$pId' AND `log_id` IN (" . implode(',', $ids) . ")");

    // Cancel the game notifications
    if (!empty($moveIds)) {
      self::DbQuery("UPDATE gamelog SET `cancel` = 1 WHERE `gamelog_move_id` IN (" . implode(',', $moveIds) . ")");
    }

    // Count the number of restarts
    if (!$logIdBreak) {
      $this->incrementStats([[$pId, 'restartTurn']]);
    }

    return $moveIds;
  }

  /*
   * getCancelMoveIds : get all cancelled move IDs from BGA gamelog, used for styling the notifications on page reload
   */
  public function getCancelMoveIds()
  {
    $moveIds = self::getObjectListFromDb("SELECT `gamelog_move_id` FROM gamelog WHERE `cancel` = 1 ORDER BY 1", true);
    return array_map('intval', $moveIds);
  }
}
