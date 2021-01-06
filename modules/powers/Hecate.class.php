<?php

class Hecate extends SantoriniPower
{
  public function __construct($game, $playerId)
  {
    parent::__construct($game, $playerId);
    $this->id    = HECATE;
    $this->name  = clienttranslate('Hecate');
    $this->title = clienttranslate('Goddess of Magic');
    $this->text  = [
      clienttranslate("[Setup:] Secretly place your Workers last. Your Workers are invisible to other players."),
      clienttranslate("[Any Time:] If an opponent attempts an action that would not be legal due to the presence of your secret Workers, their action is cancelled and they lose the rest of their turn."),
    ];
    $this->playerCount = [2]; // TODO problematic cases for 3 players: put workers last, interactions with powers and restart implementation (Limus, Harpies)...
    $this->golden  = false;
    $this->orderAid = 64;

    $this->implemented = true;
  }

  /* * */

  public function argChooseFirstPlayer(&$arg)
  {
    // Hecate must go last
    $pId = $this->getId();
    Utils::filter($arg['powers'], function ($power) use ($pId) {
      return $power != $pId;
    });
  }

  public function getPlacedWorkers()
  {
    return $this->game->board->getPlacedWorkers($this->playerId, true);
  }

  public function playerPlaceWorker($workerId, $x, $y, $z)
  {
    $worker = $this->game->board->getPiece($workerId);
    $space = ['x' => $x, 'y' => $y, 'z' => $z, 'arg' => null];

    $this->game->board->setPieceAt($worker, $space, 'secret');

    $worker = $this->game->board->getPiece($workerId); // update space
    // Notify
    $args = [
      'i18n' => ['power_name', 'piece_name'],
      'piece' => $worker,
      'piece_name' => $this->game->pieceNames[$worker['type']],
      'power_name' => $this->getName(),
      'player_name' => $this->game->getActivePlayerName(),
      'coords' => $this->game->board->getMsgCoords($space),
    ];

    $this->game->notifyPlayer($this->getPlayerId(), 'workerPlaced', $this->game->msg['powerPlacePiece'], $args);
    unset($args['piece']);
    $args['i18n'][] = 'coords';
    $args['coords'] = $this->game->specialNames['secret'];
    $this->game->notifyAllPlayers('message', $this->game->msg['powerPlacePiece'], $args);
    return true; // do not place another piece
  }

  public function argPlayerWork(&$arg, $action)
  {
    $myworkers = $this->getPlacedWorkers();
    foreach ($arg['workers'] as &$worker)
      $worker['works'] = $this->game->board->getNeighbouringSpaces($worker, $action);

    Utils::filterWorks($arg, function ($space, $piece) use ($myworkers) {
      return  !max(array_map(
        function ($s) use ($space) {
          return ($space['x'] == $s['x'] && $space['y'] == $s['y']);
        },
        $myworkers
      ));
    }); // remove Hecate worker spaces
  }

  public function argPlayerMove(&$arg)
  {
    $arg['workers'] = $this->getPlacedWorkers();
    $this->argPlayerWork($arg, 'move');
  }


  public function argPlayerBuild(&$arg)
  {
    $move = $this->game->log->getLastMove();
    if ($move == null)
      throw new BgaVisibleSystemException('Hecate build before move');

    $arg['workers'] = $this->getPlacedWorkers();
    Utils::filterWorkersById($arg, $move['pieceId']);
    $this->argPlayerWork($arg, 'build');
  }


  public function playerMove($worker, $space)
  {
    $this->game->board->setPieceAt($worker, $space, 'secret');
    $this->game->log->addMove($worker, $space);

    // Notify
    if ($space['z'] > $worker['z']) {
      $msg = $this->game->msg['moveUp'];
    } else if ($space['z'] < $worker['z']) {
      $msg = $this->game->msg['moveDown'];
    } else {
      $msg = $this->game->msg['moveOn'];
    }

    $args = [
      'i18n' => ['level_name'],
      'piece' => $worker,
      'space' => $space,
      'player_name' => $this->game->getActivePlayerName(),
      'level_name' => $this->game->levelNames[intval($space['z'])],
      'coords' => $this->game->board->getMsgCoords($worker, $space)
    ];


    $this->game->notifyPlayer($this->playerId, 'workerMoved', $msg, $args);

    $args = [
      'i18n' => ['player_name'],
      'player_name' => $this->game->getActivePlayerName(),
      'coords' => $this->game->specialNames['secret']
    ];

    $this->game->notifyAllPlayers('message', '${player_name} moves to (${coords})', $args);

    return true; // do not move again
  }


  // Return the secret worker that conflicts with this log action, or null if there is no conflict
  public function getConflictingWorker($log, $myWorkers)
  {
    Utils::filterWorkersById($myWorkers, $log['piece_id'], false);
    if (count($myWorkers) == 0) {
      return null;
    }

    $space = null;
    if (
      $log['action'] == 'move' || $log['action'] == 'force' || $log['action'] == 'build'
      || $log['action'] == 'placeWorker' || $log['action'] == 'placeToken'
      || $log['action'] == 'moveToken'
    ) {
      $args = json_decode($log['action_arg'], true);
      $space = $args['to'];
    } else if ($log['action'] == 'removal') {
      $space = $this->game->board->getPiece($log['piece_id']);
    }

    if ($space != null) {
      foreach ($myWorkers as $worker) {
        if (SantoriniBoard::isSameSpace($worker, $space)) {
          return $worker;
        }
      }
    }
    return null;
  }


  // check if the turn was legal based on Hecate power, and cancel the last actions if necessary
  // parameter: for Maenads
  public function endOpponentTurn($testOnly = false)
  {
    $myWorkers = $this->getPlacedWorkers();
    $logs = $this->game->log->logsForCancelTurn();
    $conflict = null;
    $logId = null;
    foreach (array_reverse($logs) as $log) {
      $conflict = $this->getConflictingWorker($log, $myWorkers);
      if ($conflict != null) {
        $logId = $log['log_id'];
        break;
      }
    }

    // In test mode, just return the true/false to indicate a conflict
    if ($testOnly) {
      return ($conflict == null);
    }

    // If no conflict, allow the turn to end normally
    $opponent = $this->game->playerManager->getPlayer($this->game->getActivePlayerId());
    if ($conflict == null) {
      // treat Medusa: kill secret workers only after we know the turn is legal
      foreach ($opponent->getPowers() as $power) {
        if ($power->getId() != MEDUSA) {
          continue;
        }
        $argKill = ['workers' => []];
        $power->argPlayerBuild($argKill, true); // get killable secret workers
        $power->endPlayerTurn($argKill); // kill them
      }
      return;
    }

    // Cancel the turn from this move onward
    $moveIds = $this->game->log->cancelTurn($log['log_id']);
    $conflict['z'] = $this->game->board->countBlocksAt($conflict);

    // Compute the public view (no secret pieces) and private view for each player
    $publicView = $this->game->board->getPlacedPieces();
    $privateView = [];
    $playerIds = $this->game->playerManager->getPlayerIds();
    foreach ($playerIds as $playerId) {
      $view = $this->game->board->getPlacedPieces($playerId);
      if ($view != $publicView) {
        $privateView[$playerId] = $view;
      }
    }

    // Send the public view to all (supports spectators)
    // The players with a private view coming next will ignore this notification
    $this->game->notifyAllPlayers('cancel', '', [
      'ignorePlayerIds' => array_keys($privateView),
      'placedPieces' => $publicView,
      'moveIds' => $moveIds,
    ]);

    // Send the private view to individual player(s) as needed
    foreach ($privateView as $playerId => $view) {
      $this->game->notifyPlayer($playerId, 'cancel', '', [
        'placedPieces' => $view,
        'moveIds' => $moveIds,
      ]);
    }

    // Briefly display the conflicting secret worker
    $args = [
      'ignorePlayerIds' => [$this->playerId],
      'duration' => 2000,
      'piece' => $conflict,
      'animation' => 'fadeIn',
      'i18n' => ['power_name'],
      'power_name' => $this->getName(),
      'player_name' => $this->getPlayer()->getName(),
      'player_name2' => $opponent->getName(),
      'coords' => $this->game->board->getMsgCoords($conflict),
    ];
    $this->game->notifyAllPlayers('workerPlaced', clienttranslate('${power_name}: ${player_name}\'s secret Worker (${coords}) conflicts with ${player_name2}\'s turn! The illegal actions have been cancelled.'), $args);
    unset($args['animation']);
    $this->game->notifyAllPlayers('pieceRemoved', '', $args);

    return false;
  }
}
