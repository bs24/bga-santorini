<?php

class Maenads extends SantoriniPower
{
  public function __construct($game, $playerId)
  {
    parent::__construct($game, $playerId);
    $this->id    = MAENADS;
    $this->name  = clienttranslate('Maenads');
    $this->title = clienttranslate('Raving Ones');
    $this->text  = [
      clienttranslate("[End of Your Turn:] If your Workers neighbor an opponent's Worker on opposite sides, that opponent loses the game."),
    ];
    $this->playerCount = [2, 3, 4];
    $this->golden  = false;
    $this->orderAid = 41;
  }

  /* * */
}
