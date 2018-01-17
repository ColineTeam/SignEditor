<?php
namespace Coline\SignEditor;

use pocketmine\command\Command;
use pocketmine\command\CommandSender;
use pocketmine\plugin\PluginBase;
use pocketmine\event\Listener;
use pocketmine\tile\Sign;
use pocketmine\Server;
use pocketmine\Player;
use pocketmine\event\server\DataPacketReceiveEvent;
use pocketmine\utils\TextFormat as TF;

use xenialdan\customui\network\ModalFormRequestPacket;
use xenialdan\customui\network\ModalFormResponsePacket;
use xenialdan\customui\network\ServerSettingsRequestPacket;
use xenialdan\customui\network\ServerSettingsResponsePacket;
use xenialdan\customui\windows\CustomForm;
use xenialdan\customui\elements\Input;
use xenialdan\customui\API as UIAPI;
use xenialdan\customui\event\UICloseEvent;
use xenialdan\customui\event\UIDataReceiveEvent;


/**
 * Редактирование табличек для Pocketmine by ColineTeam
 *
 * @author alexey
 */
class SignEditorMain extends PluginBase implements Listener{
    protected $scope;
    public static $uis = [];
    
    
    public function onEnable() {
        (new \ColineServices\Updater($this, 199, $this->getFile()))->update();
        $this->getServer()->getPluginManager()->registerEvents($this, $this);
    }
        
    public function onCommand(CommandSender $sender, Command $command, string $label, array $args) : bool{
        if($command->getName() == "signedit"){
            if($sender instanceof \pocketmine\command\ConsoleCommandSender){
                $sender->sendMessage("Успехов))");
                return FALSE;
            }
            if($sender instanceof \pocketmine\Player){
                $player = $sender;
                if($player->isOp()){
                    if(is_null($this->scope[$player->getName()])){
                        $player->sendMessage(TF::YELLOW."Режим редактирования табличек ".TF::GREEN."включён.".TF::YELLOW.", нажмите по табличке и откроется модальное окно редактирования. Чтобы отключить напишите комманду повторно");
                        $this->scope[$player->getName()] = true; 
                    }elseif ($this->scope[$player->getName()] == true) {
                        $player->sendMessage(TF::YELLOW."Режим редактирования табличек ".TF::RED."отключён");
                        $this->scope[$player->getName()] = FALSE; 
                    }
                    return true;
                }
            }
        }
    }
    
    public function onInteract(\pocketmine\event\player\PlayerInteractEvent $event){
        $player = $event->getPlayer();
        if(!is_null($this->scope[$player->getName()])){
            if($this->scope[$player->getName()] == true){
                if($event->getBlock()->getId() == 68 || $event->getBlock()->getId() == 63){
                    $sign = $event->getPlayer()->getLevel()->getTile($event->getBlock());
                    if($sign instanceof \pocketmine\tile\Sign){
                        $ui = new CustomForm('Редактирование табличики');
     
                        foreach ($sign->getText() as $key => $text){
                            $ui->addElement(new Input('Строка '.TF::YELLOW.'#'.($key+1), 'text', $text));
                        }
                        self::$uis[$player->getName()]['modal'] = UIAPI::addUI($this, $ui);
                        self::$uis[$player->getName()]['sign'] = $sign;

                        UIAPI::showUIbyID($this, self::$uis[$player->getName()]['modal'], $player);
                    }
                }
            }
        }
    }
    public function onPacket(DataPacketReceiveEvent $ev){
		$packet = $ev->getPacket();
		$player = $ev->getPlayer();
		switch ($packet::NETWORK_ID){
			case ModalFormResponsePacket::NETWORK_ID: {
				/** @var ModalFormResponsePacket $packet */
				$this->handleModalFormResponse($packet, $player);
				$packet->reset();
				$ev->setCancelled(true);
				break;
			}
		}
	}
        /**
	 * @group UI Response Handling
	 * @param ModalFormResponsePacket $packet
	 * @param Player $player
	 * @return bool
	 */
	public function handleModalFormResponse( $packet, \pocketmine\Player $player): bool{
		$ev = new UIDataReceiveEvent($this, $packet, $player);
		if (is_null($ev->getData())) $ev = new UICloseEvent($this, $packet, $player);
		Server::getInstance()->getPluginManager()->callEvent($ev);
		return true;
	}

	/**
	 * @param UIDataReceiveEvent $event
	 */
	public function onUIDataReceiveEvent(UIDataReceiveEvent $event){
		if($event->getPlugin() !== $this) return;
		switch ($id = $event->getID()){
			case self::$uis[$event->getPlayer()->getName()]['modal']: {
				foreach ($event->getData() as $key => $text){
                                    self::$uis[$event->getPlayer()->getName()]['sign']->setLine($key, $text);
                                }
                                $event->getPlayer()->sendPopup(TF::GREEN."Успешно".TF::YELLOW.", табличка обновлена");
				break;
			}
		}
	}
}
