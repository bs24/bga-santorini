<?php

class Zeus extends Power
{
  public function isImplemented(){ return true; }

  public static function getId() {
    return ZEUS;
  }

  public static function getName() {
    return clienttranslate('Zeus');
  }

  public static function getTitle() {
    return clienttranslate('God of the Sky');
  }

  public static function getText() {
    return [
      clienttranslate("Your Build: Your Worker may build a block under itself.")
    ];
  }

  public static function getPlayers() {
    return [2, 3, 4];
  }

  public static function getBannedIds() {
    return [];
  }

  public static function isGoldenFleece() {
    return true;
  }

  /* * */
  public function argPlayerBuild(&$arg)
  {
    foreach($arg['workers'] as &$worker){
      if($worker['z'] == 3)
        continue;

      $space = $this->game->board->getCoords($worker);
      $space['arg'] = [$space['z']];
      $worker['works'][] = $space;
    }
  }


  public function playerBuild($worker, $work)
  {
    // If space is free, we can do a classic build -> return false
    $worker2 = self::getObjectFromDB( "SELECT * FROM piece WHERE x = {$work['x']} AND y = {$work['y']} AND z = {$work['z']} AND id = {$worker['id']}");
    if ($worker2 == null)
      return false;

    // Move up the worker
    $space = $this->game->board->getCoords($worker);
    $space['z'] = $space['z'] + 1;
    if($space['z'] > 3)
      throw new BgaUserException(_("This worker would go too high (Zeus)"));
    self::DbQuery( "UPDATE piece SET x = {$space['x']}, y = {$space['y']}, z = {$space['z']} WHERE id = {$worker['id']}" );
    $this->game->log->addForce($worker, $space);

    // Build under it
    $pId = $this->game->getActivePlayerId();
    $type = 'lvl'.$work['arg'];
    self::DbQuery("INSERT INTO piece (`player_id`, `type`, `location`, `x`, `y`, `z`) VALUES ('$pId', '$type', 'board', '{$work['x']}', '{$work['y']}', '{$work['z']}') ");
    $this->game->log->addBuild($worker, $work);

    // Notify
    $piece = self::getObjectFromDB("SELECT * FROM piece ORDER BY id DESC LIMIT 1");
    $args = [
      'i18n' => [],
      'piece' => $piece,
      'playerName' => $this->game->getActivePlayerName(),
    ];
    $this->game->notifyAllPlayers('blockBuiltUnder', clienttranslate('${playerName} built a block under its worker'), $args);

    $this->game->gamestate->nextState('built');

    return true;
  }
}
