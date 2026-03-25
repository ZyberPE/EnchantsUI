<?php

declare(strict_types=1);

namespace EnchantsUI;

use pocketmine\plugin\PluginBase;
use pocketmine\player\Player;
use pocketmine\command\Command;
use pocketmine\command\CommandSender;
use pocketmine\item\Item;
use pocketmine\item\Tool;
use pocketmine\item\Armor;
use pocketmine\item\enchantment\VanillaEnchantments;
use pocketmine\item\enchantment\EnchantmentInstance;
use jojoe77777\FormAPI\SimpleForm;

class Main extends PluginBase {

    private array $selectedItem = [];
    private array $selectedEnchant = [];

    public function onEnable(): void {
        $this->saveDefaultConfig();
    }

    public function onCommand(CommandSender $sender, Command $command, string $label, array $args): bool {
        if(!$sender instanceof Player) return true;

        if($command->getName() === "enchants"){
            $this->openItemMenu($sender);
        }
        return true;
    }

    private function isEnchantable(Item $item): bool {
        return $item instanceof Tool || $item instanceof Armor;
    }

    private function openItemMenu(Player $player): void {
        $items = [];

        foreach($player->getInventory()->getContents() as $slot => $item){
            if($this->isEnchantable($item)){
                $items[$slot] = $item;
            }
        }

        if(empty($items)){
            $player->sendMessage($this->getConfig()->get("messages")["no-items"]);
            return;
        }

        $form = new SimpleForm(function(Player $player, $data) use ($items){
            if($data === null) return;

            $slots = array_keys($items);

            if(!isset($slots[$data])) return;

            $this->selectedItem[$player->getName()] = $slots[$data];
            $this->confirmItem($player);
        });

        $form->setTitle($this->getConfig()->get("titles")["item-menu"]);

        foreach($items as $item){
            $form->addButton($item->getName());
        }

        $form->addButton("§cClose");

        $player->sendForm($form);
    }

    private function confirmItem(Player $player): void {
        $form = new SimpleForm(function(Player $player, $data){
            if($data === null) return;

            if($data === 0){
                $this->openEnchantMenu($player);
            }
        });

        $form->setTitle($this->getConfig()->get("titles")["confirm-menu"]);
        $form->setContent($this->getConfig()->get("messages")["confirm-item"]);
        $form->addButton("§aYes");
        $form->addButton("§cNo");

        $player->sendForm($form);
    }

    private function openEnchantMenu(Player $player): void {
        $slot = $this->selectedItem[$player->getName()] ?? null;
        if($slot === null) return;

        $item = $player->getInventory()->getItem($slot);

        $buttons = [];

        $form = new SimpleForm(function(Player $player, $data) use (&$buttons){
            if($data === null) return;

            if(!isset($buttons[$data])) return;

            [$name, $level, $cost] = $buttons[$data];
            $this->confirmEnchant($player, $name, $level, $cost);
        });

        $form->setTitle($this->getConfig()->get("titles")["enchant-menu"]);

        foreach($this->getConfig()->get("enchants") as $name => $data){
            foreach($data["levels"] as $level => $cost){

                $enchant = $this->getEnchantment($name);
                if($enchant === null) continue;

                if(!$enchant->canBeAppliedTo($item)) continue;

                $buttons[] = [$name, (int)$level, (int)$cost];
                $form->addButton(ucfirst($name) . " " . $level . "\n§eCost: {$cost} XP");
            }
        }

        if(empty($buttons)){
            $player->sendMessage("§cNo compatible enchants!");
            return;
        }

        $player->sendForm($form);
    }

    private function confirmEnchant(Player $player, string $name, int $level, int $cost): void {
        $msg = str_replace("{cost}", (string)$cost, $this->getConfig()->get("messages")["confirm-enchant"]);

        $form = new SimpleForm(function(Player $player, $data) use ($name, $level, $cost){
            if($data === null) return;

            if($data === 0){
                $this->applyEnchant($player, $name, $level, $cost);
            }
        });

        $form->setTitle($this->getConfig()->get("titles")["cost-menu"]);
        $form->setContent($msg);
        $form->addButton("§aConfirm");
        $form->addButton("§cCancel");

        $player->sendForm($form);
    }

    private function applyEnchant(Player $player, string $name, int $level, int $cost): void {
        $playerName = $player->getName();

        if(!isset($this->selectedItem[$playerName])) return;

        $slot = $this->selectedItem[$playerName];
        $inventory = $player->getInventory();

        $item = $inventory->getItem($slot);
        if($item->isNull()) return;

        $enchant = $this->getEnchantment($name);
        if($enchant === null) return;

        if(!$enchant->canBeAppliedTo($item)){
            $player->sendMessage("§cThis enchant can't be applied to this item!");
            return;
        }

        // ✅ XP REQUIRED FOR EVERYONE (no bypass)
        if($player->getXpManager()->getXpLevel() < $cost){
            $player->sendMessage($this->getConfig()->get("messages")["not-enough-xp"]);
            return;
        }

        $player->getXpManager()->subtractXpLevels($cost);

        // ✅ APPLY ENCHANT SAFELY
        $item->addEnchantment(new EnchantmentInstance($enchant, $level));

        // ✅ SET BACK INTO SAME SLOT (NO DUPING)
        $inventory->setItem($slot, $item);

        // ✅ CLEAR DATA TO PREVENT REUSE BUG
        unset($this->selectedItem[$playerName]);

        $player->sendMessage($this->getConfig()->get("messages")["success"]);
    }

    private function getEnchantment(string $name){
        return match($name){
            "efficiency" => VanillaEnchantments::EFFICIENCY(),
            "sharpness" => VanillaEnchantments::SHARPNESS(),
            "protection" => VanillaEnchantments::PROTECTION(),
            default => null
        };
    }
}
