<?php

namespace App\Modules\Players\DTO;

use Illuminate\Notifications\DatabaseNotification;

final readonly class SystemNotificationData
{
    /**
     * @param  array<string, string>  $title
     * @param  array<string, string>  $message
     */
    public function __construct(
        public string $id,
        public string $kind,
        public array $title,
        public array $message,
        public ?string $createdAt,
        public ?string $readAt,
    ) {}

    public static function fromModel(DatabaseNotification $notification): self
    {
        /** @var array{kind: string, title: array<string, string>, message: array<string, string>} $data */
        $data = $notification->data;

        return new self(
            id: $notification->id,
            kind: $data['kind'],
            title: $data['title'],
            message: $data['message'],
            createdAt: $notification->created_at?->toISOString(),
            readAt: $notification->read_at?->toISOString(),
        );
    }

    /** @return array{id: string, kind: string, title: array<string, string>, message: array<string, string>, createdAt: string|null, readAt: string|null} */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'kind' => $this->kind,
            'title' => $this->title,
            'message' => $this->message,
            'createdAt' => $this->createdAt,
            'readAt' => $this->readAt,
        ];
    }
}
