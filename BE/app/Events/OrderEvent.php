<?php

namespace App\Events;

use App\Models\Order;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class OrderEvent implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /**
     * Create a new event instance.
     */

    public $order;
    public $connection = 'sync';

    public function __construct(Order $order)
    {
        $this->order = $order;
    }

    /**
     * Get the channels the event should broadcast on.
     *
     * @return array<int, \Illuminate\Broadcasting\Channel>
     */
    public function broadcastOn(): array
    {
        return [
            new Channel('admin-orders'),
        ];
    }

    public function broadcastAs(): string
    {
        return 'order-send';
    }

    public function broadcastWith()
    {
        return [
            'order_code' => $this->order->code,
            'final_amount' => $this->order->final_amount,
            'payment_method' => $this->order->payment_method,
            'o_name' => $this->order->o_name,
            'created_at' => $this->order->created_at->toDateTimeString(),
        ];
    }
}