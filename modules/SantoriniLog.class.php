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
    }
  }

  /*
   * gameEndStats: compute end-of-game statistics
   */
  public function gameEndStats()
  {
    $this->game->setStat($this->game->board->getCompleteTowerCount(), 'buildTower');
  }

  public function incrementStats($stats, $value = 1)
  {
    foreach ($stats as $pId => $names) {
      foreach ($names as $name) {
        if ($pId == 'table') {
          $pId = null;
        }
        $this->game->incStat($value, $name, $pId);
      }
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
   *   - array $args : action arguments (eg space)
   */
  public function insert($playerId, $pieceId, $action, $args = [])
  {
    $playerId = $playerId == -1 ? $this->game->getActivePlayerId() : $playerId;
    $round = $this->game->getGameStateValue("currentRound");
    $moveId = self::getUniqueValueFromDB("SELECT `global_value` FROM `global` WHERE `global_id` = 3");

    if ($action == 'move') {
      $args['stats'] = [
        'table' => ['move'],
        $playerId => ['move'],
      ];
      if ($args['to']['z'] > $args['from']['z']) {
        $args['stats'][$playerId][] = 'moveUp';
      } else if ($args['to']['z'] < $args['from']['z']) {
        $args['stats'][$playerId][] = 'moveDown';
      }
    } else if ($action == 'build') {
      $statName = $args['to']['arg'] == 3 ? 'buildDome' : 'buildBlock';
      $args['stats'] = [
        'table' => [$statName],
        $playerId => [$statName],
      ];
    }
    if (array_key_exists('stats', $args)) {
      $this->incrementStats($args['stats']);
    }

    $actionArgs = json_encode($args);

    // TODO : remove
    $test = self::getUniqueValueFromDB("SHOW COLUMNS FROM log WHERE Field = 'move_id'");
    if(is_null($test)){
      self::DbQuery("ALTER TABLE `log` ADD `move_id` INT(11) NOT NULL DEFAULT 0");
    }

    self::DbQuery("INSERT INTO log (`round`, `move_id`, `player_id`, `piece_id`, `action`, `action_arg`) VALUES ('$round', '$moveId', '$playerId', '$pieceId', '$action', '$actionArgs')");
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
  private function addWork($piece, $to, $action)
  {
    $args = [
      'from' => $this->game->board->getCoords($piece),
      'to'   => $to,
    ];
    $this->insert(-1, $piece['id'], $action, $args);
  }

  /*
   * addMove: add a new move entry to log
   */
  public function addMove($piece, $space)
  {
    $this->addWork($piece, $space, 'move');
  }

  /*
   * addBuild: add a new build entry to log
   */
  public function addBuild($piece, $space)
  {
    $this->addWork($piece, $space, 'build');
  }

  /*
   * addForce: add a new forced move entry to log (eg. Appolo or Minotaur)
   */
  public function addForce($piece, $space)
  {
    $this->addWork($piece, $space, 'force');
  }


  /*
   * addRemoval: add a piece removal entry to log (eg. Bia or Ares)
   */
  public function addRemoval($piece)
  {
    $this->insert(-1, $piece['id'], 'removal');
  }


  /*
   * addAction: add a new action to log
   */
  public function addAction($action, $args = [])
  {
    $this->insert(-1, 0, $action, $args);
  }


  /////////////////////////////////
  /////////////////////////////////
  //////////   Getters   //////////
  /////////////////////////////////
  /////////////////////////////////

  /*
 * getLastWorks: fetch last works of player of current round
 * params:
 *    - string $action : type of work we want to fetch (move/build)
 *    - optionnal int $pId : the player we are interested in, default is active player
 *    - optional int $limit : the number of works we want to fetched (order by most recent first), default is no-limit (-1)
 */
  public function getLastWorks($actions, $pId = null, $limit = -1)
  {
    $pId = $pId ?: $this->game->getActivePlayerId();
    $limitClause = ($limit == -1) ? '' : "LIMIT $limit";
    $actionsNames = "'" . (is_array($actions) ? implode("','", $actions) : $actions) . "'";

    $works = self::getObjectListFromDb("SELECT * FROM log WHERE `action` IN ($actionsNames) AND `player_id` = '$pId' AND `round` = (SELECT round FROM log WHERE `player_id` = $pId AND `action` = 'startTurn' ORDER BY log_id DESC LIMIT 1) ORDER BY log_id DESC " . $limitClause);

    return array_map(function ($work) {
      $args = json_decode($work['action_arg'], true);
      return [
        'action' => $work['action'],
        'pieceId' => $work['piece_id'],
        'from' => $args['from'],
        'to' => $args['to'],
      ];
    }, $works);
  }

  /*
   * getLastWork: fetch the last move/build of player of current round if it exists, null otherwise
   */
  public function getLastWork($pId = null)
  {
    $works = $this->getLastWorks(['move', 'build'], $pId, 1);
    return (count($works) == 1) ? $works[0] : null;
  }


  /*
   * getLastMoves: fetch last moves of player of current round
   */
  public function getLastMoves($pId = null, $limit = -1)
  {
    return $this->getLastWorks('move', $pId, $limit);
  }

  /*
   * getLastMove: fetch the last move of player of current round if it exists, null otherwise
   */
  public function getLastMove($pId = null)
  {
    $moves = $this->getLastMoves($pId, 1);
    return (count($moves) == 1) ? $moves[0] : null;
  }

  /*
  * getLastMoveOfWorker: fetch the last move of worker of current round if it exists, null otherwise
  */
  public function getLastMoveOfWorker($workerId)
  {
    $pId = $this->game->getActivePlayerId();
    $move = self::getObjectFromDb("SELECT * FROM log WHERE `action` = 'additionalTurn' OR (`action` = 'move' AND `piece_id` = '$workerId' AND `player_id` = '$pId' AND `round` = (SELECT round FROM log WHERE `player_id` = $pId AND `action` = 'startTurn' ORDER BY log_id DESC LIMIT 1)) ORDER BY log_id DESC LIMIT 1");
    if ($move == null || $move['action'] == 'additionalTurn') {
      return null;
    }
    return json_decode($move['action_arg'], true);
  }



  /*
   * getLastBuilds: fetch last builds of player of current round
   */
  public function getLastBuilds($pId = null, $limit = -1)
  {
    return $this->getLastWorks('build', $pId, $limit);
  }

  /*
   * getLastBuild: fetch the last build of player of current round if it exists, null otherwise
   */
  public function getLastBuild($pId = null)
  {
    $builds = $this->getLastBuilds($pId, 1);
    return (count($builds) == 1) ? $builds[0] : null;
  }


  /*
   * getLastActions : get works and actions of player (used to cancel previous action)
   */
  public function getLastActions($actions = ['move', 'build', 'skippedWork', 'usedPower', 'skippedPower'], $pId = null, $offset = null)
  {
    $pId = $pId ?: $this->game->getActivePlayerId();
    $offset = $offset ?: 0;
    $actionsNames = "'" . implode("','", $actions) . "'";

    return self::getObjectListFromDb("SELECT * FROM log WHERE `action` IN ($actionsNames) AND `player_id` = '$pId' AND `round` = (SELECT round FROM log WHERE `player_id` = $pId AND `action` = 'startTurn' ORDER BY log_id DESC LIMIT 1) - $offset ORDER BY log_id DESC");
  }

  public function getLastAction($action, $pId = null, $offset = null)
  {
    $actions = $this->getLastActions([$action], $pId, $offset);
    return count($actions) > 0 ? json_decode($actions[0]['action_arg'], true) : null;
  }


  public function getActions($actions, $pId = null)
  {
    $pId = $pId ?: $this->game->getActivePlayerId();
    $actionsNames = "'" . implode("','", $actions) . "'";

    return self::getObjectListFromDb("SELECT * FROM log WHERE `action` IN ($actionsNames) AND `player_id` = '$pId' ORDER BY log_id DESC");
  }


  public function isAdditionalTurn($pId = null)
  {
    $pId = $pId ?: $this->game->getActivePlayerId();
    $action = self::getObjectFromDb("SELECT * FROM log WHERE `action` IN ('startTurn', 'additionalTurn') ORDER BY log_id DESC LIMIT 1");
    return ($action != null && $action['player_id'] == $pId && $action['action'] == 'additionalTurn');
  }

  ////////////////////////////////
  ////////////////////////////////
  //////////   Cancel   //////////
  ////////////////////////////////
  ////////////////////////////////
  public function cancelTurn()
  {
    $pId = $this->game->getActivePlayerId();
    $logs = self::getObjectListFromDb("SELECT * FROM log WHERE `player_id` = '$pId' AND `round` = (SELECT round FROM log WHERE `player_id` = $pId AND `action` = 'startTurn' ORDER BY log_id DESC LIMIT 1) ORDER BY log_id DESC");

    $ids = [];
    $moveIds = [];
    foreach ($logs as $log) {
      $args = json_decode($log['action_arg'], true);

      if ($log['action'] == 'move' or $log['action'] == 'force') {
        // Move/force : go back to initial position
        self::DbQuery("UPDATE piece SET x = {$args['from']['x']}, y = {$args['from']['y']}, z = {$args['from']['z']} WHERE id = {$log['piece_id']}");
      } else if ($log['action'] == 'build') {
        // Build : remove the piece
        self::DbQuery("DELETE FROM piece WHERE x = {$args['to']['x']} AND y = {$args['to']['y']} AND z = {$args['to']['z']}");
      } else if ($log['action'] == 'removal') {
        // Removal : put the piece back on the board
        self::DbQuery("UPDATE piece SET location = 'board' WHERE id = {$log['piece_id']}");
      }

      if (array_key_exists('stats', $args)) {
        // Undo statistics
        $this->incrementStats($args['stats'], -1);
      }

      $ids[] = intval($log['log_id']);
      if ($log['action'] != 'startTurn') {
        $moveIds[] = array_key_exists('move_id', $log)? intval($log['move_id']) : 0; // TODO remove the array_key_exists
      }
    }

    // Remove the logs
    self::DbQuery("DELETE FROM log WHERE `player_id` = '$pId' AND `log_id` IN (" . implode(',', $ids) . ")");

    // TODO : remove
    $test = self::getUniqueValueFromDB("SHOW COLUMNS FROM gamelog WHERE Field = 'cancel'");
    if(is_null($test)){
      self::DbQuery("ALTER TABLE `gamelog` ADD `cancel` TINYINT(1) NOT NULL DEFAULT 0");
    }

    // Cancel the game notifications
    self::DbQuery("UPDATE gamelog SET `cancel` = 1 WHERE `gamelog_move_id` IN (" . implode(',', $moveIds) . ")");
    return $moveIds;
  }

  /*
   * getCancelMoveIds : get all cancelled move IDs from BGA gamelog, used for styling the notifications on page reload
   */
  public function getCancelMoveIds()
  {
    // TODO : remove
    $test = self::DbQuery("SHOW COLUMNS FROM gamelog WHERE Field = 'cancel'");
    if(empty($test)){
      self::DbQuery("ALTER TABLE `gamelog` ADD `cancel` TINYINT(1) NOT NULL DEFAULT 0");
    }


    // TODO : remove
    $test = self::getUniqueValueFromDB("SHOW COLUMNS FROM gamelog WHERE Field = 'cancel'");
    if(is_null($test)){
      self::DbQuery("ALTER TABLE `gamelog` ADD `cancel` TINYINT(1) NOT NULL DEFAULT 0");
    }

    $moveIds = self::getObjectListFromDb("SELECT `gamelog_move_id` FROM gamelog WHERE `cancel` = 1 ORDER BY 1", true);
    return array_map('intval', $moveIds);
  }
}
