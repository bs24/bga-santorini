<?php

class Bia extends SantoriniPower
{
  public function __construct($game, $playerId)
  {
    parent::__construct($game, $playerId);
    $this->id    = BIA;
    $this->name  = clienttranslate('Bia');
    $this->title = clienttranslate('Goddess of Violence');
    $this->text  = [
      clienttranslate("Setup: Place your Workers first."),
      clienttranslate("Your Move: If your Worker moves into a space and the next space in the same direction is occupied by an opponent Worker, the opponent's Worker is removed from the game.")
    ];
    $this->players = [2, 3, 4];
    $this->golden  = true;

    $this->implemented = true;
  }

  /* * */

  // TODO setup: 1st player


  public function afterPlayerMove($worker, $work)
  {
    $x = 2 * $work['x'] - $worker['x'];
    $y = 2 * $work['y'] - $worker['y'];

    // If there is no opponent in the next space -> return null
    $worker2 = self::getObjectFromDB("SELECT * FROM piece WHERE x = {$x} AND y = {$y} AND type = 'worker'");
    if ($worker2 == null || $worker2['player_id'] == $worker['player_id']) {
      return;
    }

    $this->game->playerKill($worker2, $this->getName());
  }
}
