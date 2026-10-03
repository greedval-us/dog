<?php

namespace App\Modules\Pets\Enums;

enum CareRefusal: string
{
    case TrainingNeeds = 'training_needs';
    case Energy = 'energy';
    case ActiveNeeds = 'active_needs';
    case NeedFull = 'need_full';
    case TrainingPotential = 'training_potential';
    case PlayerBlocked = 'player_blocked';
    case Inactive = 'inactive';
    case Busy = 'busy';
    case Cooldown = 'cooldown';
    case ItemsRequired = 'items_required';
    case ItemUnavailable = 'item_unavailable';
    case NotReady = 'not_ready';

    public function message(): string
    {
        return match ($this) {
            self::TrainingNeeds => 'Training requires health, satiety and hydration of at least 50%.',
            self::Energy => 'Not enough energy. Let your dog rest first.',
            self::ActiveNeeds => 'Feed your dog and offer water before active play or a walk.',
            self::NeedFull => 'This need is already full. Choose another action.',
            self::TrainingPotential => 'Your dog has reached the potential for this training.',
            self::PlayerBlocked => 'This player cannot care for pets.',
            self::Inactive => 'This dog is no longer active.',
            self::Busy => 'Your dog is busy or retired.',
            self::Cooldown => 'This action is cooling down. Wait before trying again.',
            self::ItemsRequired => 'Select the required items from your inventory.',
            self::ItemUnavailable => 'A selected item is unavailable. Choose another item.',
            self::NotReady => 'This activity is not ready to finish.',
        };
    }
}
