<?php

namespace HannesTheDev;

use pocketmine\plugin\PluginBase;
use pocketmine\event\Listener;
use poketmine\Player;
use pocketmine\event\server\DataPacketReceiveEvent;
use pocketmine\network\mcpe\protocol\ModalFormResponsePacket;
use pocketmine\command\Command;
use pocketmine\command\CommandSender;
use pocketmine\item\Item;
use pocketmine\event\player\PlayerQuitEvent;
use HannesTheDev\FormEvent\Form;
use HannesTheDev\FormEvent\CustomForm;
use onebone\economyapi\EconomyAPI;

class Main extends PluginBase implements Listener{
  
  public $used;
  public $eco;
  public $giftcode;
  public $instance;
  public $formCount = 0;
  public $forms = [];
  
  public function onEnable(){
    $this->getLogger()->info("Plugin activated");
    $plugin = $this->getPluginManager()->getPlugin("EconoyAPI");
    if(is_null($plugin)){
      $this->getLogger()->info("You must installing EconomyAPI");
      $this->getServer()->shutdown();
    } else {
      $this->eco = EconomyAPI::getInstance();
    }
    $this->formCount = rand(0, 0xFFFFFFFF);
    $this->getServer()->getPluginManager()->registerEvents($this, $this);
    if(!is_dir($this->getDataFolder())){
      mkdir($this->getDataFolder());
    }
    $this->used = new \SQLite3($this->getDataFolder() . "used-code.db");
    $this->used->exec("CREATE TABLE IF NOT EXISTS code (code);");
    $this->giftcode = new \SQLite3($this->getDataFolder() ."code.dn");
    $this->giftcode->exec("CREATE TABLE IF NOT EXISTS code (code);");
  }
  
  public function createSimpleForm(callable $funcion = null) : CustomForm{
    $this->formCountBump();
    $form = new CustomForm($this->formCount, $function);
    $this->forms[$this->formCount] = $form;
    return $form;
  }
  
  public function formCountBump() : void{
    ++$this->formCount;
    if($this->formCount & (1 << 32)){
      $this->formCount = rand(0, 0xFFFFFFFF);
    }
  }
  
  public function onPackedReveived(DataPacketReceviedEvent $ev) : void{
    $pk = $ev->getPacket();
    if($pk instanceof ModalFormReponsePacket){
      $player = $ev->getPacket();
      $formId = $pk->formId;
      $data = json_decode($pk->formData, true);
      if(isset($this->forms[$formId])){
        $form = $this->forms[$formId];
        if(!$form->Recipient($player)){
          return;
        }
        $callable = $form->getCallable();
        if(!is_array($data)){
          $data = [$data];
        }
        if($callable !== null){
          $callable($ev->getPlayer(), $data);
        }
        unset($this->forms[$formId]);
        $ev->setCancelled();
      }
    }
  }
  
  public function onPlayerQuit(onPlayerQuitEvent $ev){
    $player = $ev->getPlayer();
    foreach($this->forms as $id => $forms){
      if($form->istRecipient($player)){
        unset($this->forms[$id]);
        break;
      }
    }
  }
  
  public function RedeemMenu($player){
    if($player instanceof Player){
      $form = $this->createCustomForm(function(Player $player, array $data){
        $result = $data[0];
        if($result != null){
          if($this->codeExists($this->giftcode, $result)){
            if(!($this->codeExists($this->used, $result))){
              $chance = mt_rand(1, 5);
              $this->addCode($this->used, $result);
              switch($chance){
                case 5:
                  $keys = array_rand(Item::$list, 4);
                  for($i = 0; $i <= 3; ++$i){
                    $itme = Item::get($keys[$i], 0, 1);
                    $player->addItem($item);
                    $player->sendMessage("§8[§cGiftCode§8] §7You've successfully §aredeem §7the code and get now §a" . $item->getName() . "§7!");
                  }
                  break;
                case 4:
                  $player->sendMessage("§8[§cGiftCode§8] §7You've successfully §aredeem §7the code and get now §a20.000$§7!");
                  $this->eco->addMoney($player->getName(), 20000)
                  break;
                default:
                  $player->sendMessage("§8[§cGiftCode§8] §cThe code could not be found, please try again!");
                  break;
              }
            } else {
              $player->sendMessage("§8[§cGiftCode§8] §cYou've already used this code!");
              return true;
            }
          } else {
            $player->sendMessage("§8[§cGiftCode§8] §cThe gift code you used was not found!");
            return true;
          }
        } else {
          $player->sendMessage("§8[§cGiftCode§8] §cYou've must write a code in the line to get a gift!");
          return true;
        }
      });
      $form->setTitle("§8[§cRedeemMenu§8]");
      $form->setInput("§7Enter the code you want to redeem in the column below:");
      $form->sendToPlayer($player);
    }
  }
  
  public static function getInstance(){
    return $this;
  }
  
  public function generateCode(){
    $characters = "0123456789ABCDEFGHIJKLNMOPQRSTUFWXYZabcdefghijklnmopqrstufwxyz";
    $charactersLength = strlen($characters);
    $lenght = 10;
    $randomString = "2021";
    for($i = 0; $i < $length; $i++){
      $randomString .= $characters[rand(0, $charactersLength - 1)];
    }
    $this->addCode($this->giftcode, $randomString);
    return $randomString;
  }
  
  public function codeExists($file, $code){
    $query = $file->query("SELECT * FROM code WHERE code='$code';");
    $ar = $query->fetchArray(SQLITE3_ASSOC);
    if(!empty($ar)){
      return true;
    } else {
      return false;
    }
  }
  
  public function addCode($file, $code) {
    $stmt = $file->prepare("INSERT OR REPLACE INTO code (code) VALUES (:code);");
    $stmt->bindValue(":code", $code);
    $stmt->execute();
  }
  
  public function onCommand(CommandSender $player, Command $command, string $label, array $args) : bool{
    switch($comand->getName()){
      case "gencode":
        if($player->hasPermission("gencode.command")){
          $code = $this->generateCode();
          $player->sendMessage("§8[§cGiftCode§8] §7You've successfully §agenerated §7a gift code! §aCode: §c" . $code);
        } else {
          $player->sendMessage("§8[§cGiftCode§8] §cYou haven't permission to use this command!")
        }
        break;
      case "redeem":
        if($player instanceof Player){
          $this->RedeemMenu($player);
        } else {
          $player->sendMessage("§8[§cGiftCode§8] §cYou must be a player to use this command!")
        }
    }
    return true;
  }
}