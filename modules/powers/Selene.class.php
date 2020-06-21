<?php

class Selene extends SantoriniPower
{
  public function __construct($game, $playerId)
  {
    parent::__construct($game, $playerId);
    $this->id    = SELENE;
    $this->name  = clienttranslate('Selene');
    $this->title = clienttranslate('Goddess of the Moon');
    $this->text  = [
      clienttranslate("[Your Build:] Instead of your normal build, your female Worker may build a dome at any level regardless of which Worker moved. ")
    ];
    $this->playerCount = [2, 3, 4];
    $this->golden  = true;
    $this->orderAid = 8;

    $this->implemented = true;
  }

  /* * */
  public function argPlayerPlaceWorker(&$arg)
  {
    $arg['displayType'] = true;
  }


  protected function updateBuildArg(&$worker, $add)
  {
    foreach ($worker['works'] as &$work) {
      if ($add) {
        if (!in_array(3, $work['arg'])) {
          $work['arg'][] = 3;
        }
      } else {
        $work['arg'] = [3];
      }
    }
  }

  public function argPlayerBuild(&$arg)
  {
    $fworkers = $this->game->board->getPlacedActiveWorkers('f');
    if (count($fworkers) == 0) {
      return;
    }

    $move = $this->game->log->getLastMove();
    foreach ($fworkers as &$fworker) {
      $worker = &Utils::getWorkerOrCreate($arg, $fworker);
      $worker['works'] = $this->game->board->getNeighbouringSpaces($worker, 'build');
      $this->updateBuildArg($worker, $worker['id'] == $move['pieceId']);
    }
  }

  public function playerBuild($worker, $work)
  {
    $move = $this->game->log->getLastMove();
    if (substr($worker['type_arg'], 0, 1) == 'f' && $work['arg'] == 3 && ($work['z'] != 3 || $worker['id'] != $move['pieceId'])) {
      // Female built dome on non-standard level or without moving
      $stats = [[$this->playerId, 'usePower']];
      $this->game->log->addAction('stats', $stats);
    }
  }
}
