<?php

namespace App\Http\Resources;

use App\Models\Branch;
use App\Models\BranchOpeningHour;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Branch */
class BranchResource extends JsonResource
{
    private const TIMEZONE = 'Asia/Jakarta';

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $hours = $this->openingHours->sortBy('day_of_week')->values();

        return [
            'id' => $this->id,
            'name' => $this->name,
            'address' => $this->address,
            'description' => $this->description,
            'phone' => $this->phone,
            'image_url' => $this->image_url ? (str_starts_with($this->image_url, 'http') ? $this->image_url : asset($this->image_url)) : null,
            'order_types' => ['dine_in', 'takeaway'],
            'is_open_now' => $this->isOpenNow($hours),
            'opening_hours' => $hours->map(fn (BranchOpeningHour $hour) => [
                'day_of_week' => $hour->day_of_week,
                'open_time' => $this->formatTime($hour->open_time),
                'close_time' => $this->formatTime($hour->close_time),
                'is_closed' => $hour->is_closed,
            ]),
        ];
    }

    private function isOpenNow($hours): bool
    {
        $now = now(self::TIMEZONE);
        $today = $hours->firstWhere('day_of_week', $now->dayOfWeekIso);

        if (! $today || $today->is_closed || ! $today->open_time || ! $today->close_time) {
            return false;
        }

        $time = $now->format('H:i');

        return $time >= $this->formatTime($today->open_time)
            && $time <= $this->formatTime($today->close_time);
    }

    private function formatTime(?string $time): ?string
    {
        return $time ? substr($time, 0, 5) : null;
    }
}
