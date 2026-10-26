<?php

declare(strict_types=1);

namespace App\Dashboard;

use Illuminate\Validation\Validator;

/**
 * A week of opening hours as the dashboard's hours editor sends it: every day,
 * keyed by Carbon's day number (0 = Sunday), so a day left out cannot be
 * mistaken for a closed one. Validated the same way wherever it is sent, on a
 * location's Hours pages and in the add location wizard.
 */
final readonly class WeeklyHours
{
    /**
     * The end of a day open around the clock, as the time input sends it.
     * Stored as 23:59:59, the last moment the schedule parser counts as open.
     */
    public const string END_OF_DAY = '23:59';

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(string $key): array
    {
        return [
            $key => ['required', 'array:0,1,2,3,4,5,6', 'size:7'],
            "{$key}.*" => ['required', 'array:is_open,open_time,close_time'],
            "{$key}.*.is_open" => ['required', 'boolean'],
            "{$key}.*.open_time" => ['nullable', "required_if_accepted:{$key}.*.is_open", 'date_format:H:i'],
            "{$key}.*.close_time" => ['nullable', "required_if_accepted:{$key}.*.is_open", 'date_format:H:i'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(string $key): array
    {
        return [
            "{$key}.*.open_time.required_if_accepted" => 'Choose an opening time.',
            "{$key}.*.close_time.required_if_accepted" => 'Choose a closing time.',
            "{$key}.*.open_time.date_format" => 'Enter a time like 08:00.',
            "{$key}.*.close_time.date_format" => 'Enter a time like 17:00.',
        ];
    }

    /**
     * An open day must close after it opens; hours past midnight are not
     * supported by the schedule tables. Run once the rules above pass.
     */
    public function checkCloseAfterOpen(Validator $validator, string $key): void
    {
        foreach ($validator->getData()[$key] ?? [] as $day => $hours) {
            if (filter_var($hours['is_open'], FILTER_VALIDATE_BOOLEAN) && $hours['close_time'] <= $hours['open_time']) {
                $validator->errors()->add("{$key}.{$day}.close_time", 'Must be after the opening time.');
            }
        }
    }

    /**
     * Each day's hours as the schedule tables store them, or null when closed.
     *
     * @param  array<int|string, array{is_open: bool|string|int, open_time: string|null, close_time: string|null}>  $days  Validated by rules().
     * @return array<int, array{open_time: string, close_time: string}|null>
     */
    public function toSchedule(array $days): array
    {
        $schedule = [];

        foreach ($days as $day => $hours) {
            $schedule[(int) $day] = filter_var($hours['is_open'], FILTER_VALIDATE_BOOLEAN)
                ? [
                    'open_time' => $hours['open_time'].':00',
                    'close_time' => $hours['close_time'] === self::END_OF_DAY ? '23:59:59' : $hours['close_time'].':00',
                ]
                : null;
        }

        ksort($schedule);

        return $schedule;
    }
}
