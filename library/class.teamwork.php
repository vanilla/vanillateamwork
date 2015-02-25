<?php

/**
 * @copyright 2010-2014 Vanilla Forums Inc
 * @license Proprietary
 */

/**
 * Teamwork API wrapper
 *
 * @author Tim Gunter <tim@vanillaforums.com>
 * @package internal
 * @subpackage Teamwork
 * @since 1.0
 */
class Teamwork {

    /**
     * UTC DateTimeZone for date math
     * @var DateTimeZone
     */
    public static $utc;

    /**
     * Get current UTC date
     *
     * @return string
     */
    public static function now() {
        return Orchestration::utc()->format('Y-m-d H:i:s');
    }

    /**
     * Get current UTC date object
     *
     * @return DateTime
     */
    public static function utc() {
        $now = new DateTime('now', self::utcTZ());
        return $now;
    }

    /**
     * Get UTC Timezone
     *
     * @return DateTimeZone
     */
    public static function utcTZ() {
        if (!(self::$utc instanceof DateTimeZone)) {
            self::$utc = new DateTimeZone('UTC');
        }
        return self::$utc;
    }

    /**
     * Get a DateTime object (in UTC) for given string or integer time
     *
     * @param string|integer $time datetime in 'Y-m-d H:i:s' or 'U' format
     * @return DateTime
     */
    public static function date($time) {
        $format = is_numeric($time) ? 'U' : 'Y-m-d H:i:s';
        if (strtotime($time) <= 0) {
            return null;
        }
        return DateTime::createFromFormat($format, $time, self::utcTZ());
    }

    /**
     * Get DateTime object (in UTC) for given string time
     *
     * Arguments support in strtotime() format.
     *
     * @param string $time
     * @return DateTime
     */
    public static function time($time) {
        $t = strtotime($time);
        if (!$t) {
            return null;
        }
        return DateTime::createFromFormat('U', $t, self::utcTZ());
    }

    /**
     * Parse a duration string into seconds
     *
     * Interval formats are "5d" or "-10m", etc.
     *
     * @param string $duration
     * @return integer seconds
     */
    public static function parseDuration($duration) {
        if (is_integer($duration)) {
            return $duration;
        }

        $multipliers = [
            's' => 1,
            'm' => 60,
            'h' => 3600,
            'd' => 86400,
            'w' => 604800
        ];

        $time = 0;
        $parts = explode(' ', $duration);
        foreach ($parts as $part) {
            $partFactor = (int)$part;
            $partMultiplier = str_replace($partFactor,'',$part);
            if (!array_key_exists($partMultiplier, $multipliers)) {
                continue;
            }
            $multiplier = $multipliers[$partMultiplier];
            $time += $partFactor * $multiplier;
        }
        return $time;
    }

    /**
     * Get a DateInterval from a fuzzy shorthand
     *
     * Interval formats are "5d" or "-10m", etc.
     *
     * @param string $interval
     * @return DateInterval|null
     */
    public static function interval($interval) {
        $frequencies = array(
            's' => 'seconds',
            'i' => 'minutes',
            'h' => 'hours',
            'd' => 'days',
            'w' => 'weeks',
            'm' => 'months',
            'y' => 'years'
        );

        $iterations = (int)$interval;
        $frequencyKey = substr($interval, -1, 1);
        $frequency = val($frequencyKey, $frequencies, null);

        if (is_null($frequency)) {
            return false;
        }

        return DateInterval::createFromDateString("{$iterations} {$frequency}");
    }

    /**
     * Make teamwork API request
     *
     * @param string $method
     * @param string $action
     * @param array $parameters optional.
     * @return CommunicationRequest
     */
    public static function request($method, $action, $parameters = null) {
        $destination = C('Teamwork.API.Destination');
        $api = C('Teamwork.API.Token');

        $cr = Communication::generic("{$action}.json")
                ->server($destination)
                ->secure(true)
                ->method($method)
                ->authorization($api, 'x');

        if (is_array($parameters)) {
            $cr->parameter($parameters);
        }

        return $cr;
    }

    /**
     * Get teardown tasks
     *
     * @param DateTime $startDate
     * @param DateTime $endDate
     *
     * @param boolean $fresh optional. force the cache to be cleared. default false.
     */
    public static function tearTasks($startDate, $endDate, $completed = true, $fresh = false) {
        $request = Teamwork::request('get', 'tasks', [
            'startdate' => $startDate->format('Ymd'),
            'enddate' => $endDate->format('Ymd')
        ]);

        if ($completed) {
            $request->parameter('includeCompletedTasks', true);
        }

        $request->cache(3600);
        $request->flushCache($fresh);

        $tasks = $request->send();
        if (!$request->responseClass('200')) {
            return false;
        }

        return $tasks['todo-items'];
    }

    /**
     * Get parsed breakdown for a given week
     *
     * @param string $week optional. specify a date to get breakdown for that week.
     * @param boolean $fresh optional. force the cache to be cleared. default false.
     */
    public static function getBurndown($week = null, $fresh = false) {

        if (is_null($week)) {
            $week = 'monday this week';
        }

        $dates = Teamwork::getWeek($week);
        if (!$dates) {
            return false;
        }
        $startDate = $dates['startdate'];
        $endDate = $dates['enddate'];

        $tt = Teamwork::tearTasks($startDate, $endDate, true, $fresh);

        // Prepare data structure
        $burndown = [
            'startdate' => $startDate->format('Y-m-d'),
            'enddate' => $endDate->format('Y-m-d'),
            'initial-minutes' => 0,
            'spike-minutes' => 0,
            'estimated-minutes' => 0,
            'burned-down' => 0,
            'days' => [],
            'events' => []
        ];

        // Add day keys with placeholder data structures
        $walkDate = clone $startDate;
        $interval = Teamwork::interval('1d');
        for ($i = 1; $i <= 5; $i++) {
            if ($i > 1) {
                $walkDate->add($interval);
            }
            $dayKey = $walkDate->format('Ymd');

            $burndown['days'][$dayKey] = [
                'key' => $dayKey,
                'index' => $i,
                'label' => $walkDate->format('l'),
                'carried-over' => 0,
                'ideal' => [
                    'spike-minutes' => 0,
                    'total-minutes' => 0,
                ],
                'burned-down' => 0
            ];
        }

        // Keep track of things to do
        $adjustments = [];

        $startDayKey = $startDate->format('Ymd');
        $endDayKey = $endDate->format('Ymd');

        // Iterate tasks and extract estimated and broken down minutes
        foreach ($tt as $task) {

            // Require estimates
            if (!$task['estimated-minutes']) {
                continue;
            }

            // Require due date
            if (!$task['due-date']) {
                continue;
            }

            $taskDueDate = Teamwork::time($task['due-date']);
            $taskDueKey = $taskDueDate->format('Ymd');

            // Task is not due this week
            if ($taskDueKey > $endDayKey) {
                continue;
            }

            if ($task['completed']) {
                $taskCompletedDate = Teamwork::time($task['completed_on']);
                $taskCompletedKey = $taskCompletedDate->format('Ymd');

                // Task was completed in a previous sprint
                if ($taskCompletedDate < $startDate) {

                    // If this is a refresh, adjust task into correct sprint slot
                    if ($fresh) {
                        $sprintWeek = Teamwork::getWeek($taskCompletedDate);
                        $adjustments[] = [
                            'taskid' => $task['id'],
                            'modify' => [
                                'due-date' => $sprintWeek['enddate']->format('Ymd')
                            ]
                        ];
                    }

                    continue;
                }
            }

            // Add to total minutes
            $burndown['estimated-minutes'] += $task['estimated-minutes'];

            $taskStartDate = Teamwork::time($task['created-on']);
            $taskStartKey = $taskStartDate->format('Ymd');

            // Task is a scope increase
            if ($taskStartKey > $startDayKey) {
                $burndown['days'][$taskStartKey]['ideal']['spike-minutes'] += $task['estimated-minutes'];
                $burndown['spike-minutes'] += $task['estimated-minutes'];

                Teamwork::addEvent($burndown, $taskStartDate, $task);

            // Task was allocated during sprint
            } else {
                $burndown['initial-minutes'] += $task['estimated-minutes'];
            }

            // Task is burned down
            if ($task['completed']) {

                if ($taskCompletedKey >= $startDayKey && $taskCompletedKey <= $endDayKey) {
                    $burndown['days'][$taskCompletedKey]['burned-down'] += $task['estimated-minutes'];
                    $burndown['burned-down'] += $task['estimated-minutes'];
                }

            }

        }

        // Parse burndown and spread minutes over days
        $days = count($burndown['days']);
        $carry = $burndown['initial-minutes'];
        $onDay = 0;
        $max = $burndown['initial-minutes'];
        foreach ($burndown['days'] as $dayKey => &$day) {
            $max += $day['ideal']['spike-minutes'];
            $day['ceiling-minutes'] = $max;

            $day['carried-over'] = $carry;
            $day['ideal']['total-minutes'] = $day['carried-over'] + $day['ideal']['spike-minutes'];

            $daysLeft = $days - $onDay;
            if ($daysLeft) {
                $idealBurn = $day['ideal']['total-minutes'] / $daysLeft;
            } else {
                $idealBurn = $day['ideal']['total-minutes'];
            }
            $day['ideal']['burn-minutes'] = $idealBurn;
            $remain = $day['ideal']['total-minutes'] - $idealBurn;
            $carry = $day['ideal']['end-minutes'] = $remain;

            $onDay++;
        }

        // Make updates
        if ($fresh && count($adjustments)) {

            $burndown['adjustments'] = $adjustments;
            foreach ($burndown['adjustments'] as &$adjustment) {
                $taskID = $adjustment['taskid'];
                $modify = $adjustment['modify'];

                $update = Teamwork::request('put', "/tasks/{$taskID}.json");
                $update->parameter('todo-item', $modify);
                $updated = $update->send();
                $adjustment['response'] = $updated;
            }

        }

        return $burndown;
    }

    /**
     * Get the week for a given time
     *
     * @param string|DateTime $time
     * @return array
     */
    public static function getWeek($time) {
        if (!($time instanceof DateTime)) {
            $timeDate = Teamwork::time($time);
            if (!$timeDate) {
                return false;
            }
        } else {
            $timeDate = $time;
        }

        $startDate = clone $timeDate;

        // Adjust startdate to fall on the Monday
        $day = $startDate->format('N');
        if ($day > 1) {
            $days = $day - 1;
            $interval = Teamwork::interval("{$days}d");
            $startDate->sub($interval);
        }
        $startDate->setTime(0, 0, 0);

        // Adjust enddate to fall on Friday at midnight
        $endInterval = Teamwork::interval('4d');
        $endDate = clone $startDate;
        $endDate->add($endInterval);
        $endDate->setTime(23, 59, 59);

        return [
            'time' => $timeDate,
            'startdate' => $startDate,
            'enddate' => $endDate
        ];
    }

    /**
     * Add a scope change event
     *
     * @param array $burndown
     * @param string $date
     * @param array $task
     */
    public static function addEvent(&$burndown, $date, $task) {
        $key = $date->format('Ymd');
        if (!array_key_exists($key, $burndown['events'])) {
            $burndown['events'][$key] = [
                'date' => $date->format('Y-m-d H:i:s'),
                'events' => []
            ];
        }
        $eventDay = &$burndown['events'][$key]['events'];

        $projectID = $task['project-id'];
        if (!array_key_exists($projectID, $eventDay)) {
            $eventDay[$projectID] = [
                'project' => $task['project-name'],
                'project-id' => $projectID,
                'events' => 0,
                'minutes' => 0
            ];
        }

        $eventDay[$projectID]['minutes'] += $task['estimated-minutes'];
        $eventDay[$projectID]['events']++;
    }

}