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

  public function argPlayerPlaceWorker(&$arg)
  {
    $arg['location'] = 'secret';
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
    if ($move == null) {
      throw new BgaVisibleSystemException('Hecate build before move');
    }

    $arg['workers'] = $this->getPlacedWorkers();
    Utils::filterWorkersById($arg, $move['pieceId']);
    $this->argPlayerWork($arg, 'build');
  }

  public function playerMove($worker, $space)
  {
    return ['powerId' => HECATE, 'location' => 'secret'];
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
    foreach (array_reverse($logs) as $log) {
      $conflict = $this->getConflictingWorker($log, $myWorkers);
      if ($conflict != null) {
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
    $this->game->cancelPreviousWorks($log['log_id']);

    // Briefly display the conflicting secret worker
    // Always show as female
    $conflict['z'] = $this->game->board->countBlocksAt($conflict);
    $conflict['name'] = 'f' . substr($conflict['name'], 1);
    $conflict['type_arg'] = 'f' . substr($conflict['type_arg'], 1);
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
    $this->game->notifyAllPlayers('workerPlaced', clienttranslate('${power_name}: ${player_name}\'s secret Worker (${coords}) conflicts with ${player_name2}\'s turn! The illegal actions have been cancelled and the rest of the turn is lost.'), $args); // add "rest of the turn" to clarify why Hydra / Medusa / Charybdis / Maenads etc do not activate their power at the end
    unset($args['animation']);
    $this->game->notifyAllPlayers('pieceRemoved', '', $args);
  }
}
