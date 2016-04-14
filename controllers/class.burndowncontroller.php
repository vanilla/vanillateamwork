<?php

/**
 * @copyright 2010-2014 Vanilla Forums Inc
 * @license Proprietary
 */

/**
 * Burndown Management Controller
 *
 * This controller provides the burndown user interface, including the
 * sprint burndown overview chart.
 *
 * It also responds to AJAX calls for refresh and flush operations.
 *
 * @author Tim Gunter <tim@vanillaforums.com>
 * @package infrastructure
 * @subpackage vfteamwork
 * @since 1.0
 */
class BurndownController extends VanillaConsoleController {

    public function initialize() {
        $this->Head = new HeadModule($this);
        $this->addJsFile('jquery.js');
        $this->addJsFile('jquery.livequery.js');
        $this->addJsFile('jquery.form.js');
        $this->addJsFile('jquery.popup.js');
        $this->addJsFile('jquery.gardenhandleajaxform.js');
        $this->addJsFile('global.js');
        $this->addJsFile('mustache/mustache.js');

        //$this->addCssFile('flyout.css');
        $this->addCssFile('inform.css');

        $this->addBreadcrumb('Teamwork', url('/burndown'));

        $this->form = new Gdn_Form();
        $this->Form = &$this->form;

        parent::initialize();
    }

    /**
     * Alias for Teamwork::burndown()
     *
     */
    public function index() {
        redirect("/burndown/overview");
    }

    /**
     * Burndown chart
     *
     * Returns JSON or UI of burndown chart.
     */
    public function overview($week = null) {
        $this->permission('vfteamwork.burndown.view');
        $this->addSideMenu('burndown/overview');
        $this->addBreadcrumb('Burndown');
        $this->title('Burndown');

        // Add vfconsole js libaries
        $this->addJsFile('jquery.dial.js', 'vfconsole');
        $this->addJsFile('active.js', 'vfconsole');
        $this->addJsFile('activedial.js', 'vfconsole');
        $this->addJsFile('graphing.js', 'vfconsole');
        $this->addJsFile('analytics.js', 'vfconsole');

        // Add core js libraries
        $this->addJsFile('raphael/raphael.min.js');
        $this->addJsFile('elycharts/elycharts.min.js');
        $this->addJsFile('jquery-ui.min.js');

        // Add core js libaries
        $this->addJsFile('jquery-ui.min.js');

        // Add vanillicon CSS
        $this->addCssFile('/resources/css/vanillicon.css');

        // Add Mustache templates for js usage
        MustachePlugin::addPartial('viewcompletion');

        $this->addJsFile('burndown/burndown.js');

        $burndown = Teamwork::getBurndown($week);
        $this->setData('burndown', $burndown);

        // Prepare the sprint progress for each workers
        $this->setData('workers-progress', $this->computeWorkersProgress($burndown));

        $this->render();
    }

    /**
     * Overview for on-screen display
     *
     * @param string $week optional
     */
    public function display($week = null) {
        $this->View = 'overview';
        $this->overview($week);
    }

    /**
     *
     * @param type $week
     */
    public function tasks($week = null) {
        $this->permission('vfteamwork.burndown.view');
        $this->deliveryMethod(DELIVERY_METHOD_JSON);
        $this->deliveryType(DELIVERY_TYPE_DATA);

        $burndown = Teamwork::getBurndown($week);
        $this->setData('burndown', $burndown);

        $startDate = Teamwork::time($burndown['startdate']);
        $endDate = Teamwork::time($burndown['enddate']);
        $tasks = Teamwork::tearTasks($startDate, $endDate);
        $this->setData('tasks', $tasks);

        $this->render();
    }

    /**
     * Burndown analytics
     *
     * @throws Exception
     */
    public function burndownData($week = null) {
        $this->permission('vfteamwork.burndown.view');
        $this->deliveryMethod(DELIVERY_METHOD_JSON);
        $this->deliveryType(DELIVERY_TYPE_DATA);

        // Prepare graph data

        $increments = 6;
        $today = Console::utc();
        $todayKey = $today->format('Ymd');

        // API for data

        $burndown = Teamwork::getBurndown($week);
        $this->setData('burndown', $burndown);

        $startDate = Console::time($burndown['startdate'].' 23:59:59');
        $startKey = $startDate->format('Ymd');
        $endDate = Console::time($burndown['enddate'].' 23:59:59');
        $endKey = $endDate->format('Ymd');

        $drawDate = clone $endDate;
        $drawDate->add(Console::interval('1d'));
        $drawKey = $drawDate->format('Ymd');

        $capDate = clone $endDate;
        $capDate->add(Console::interval('2d'));
        $capKey = $capDate->format('Ymd');

        // Prepare response

        $series = [];

        $burnSeries = [];
        $todaySeries = [];

        $burnSeries = array_fill(0, $increments, null);
        $burnSeries[0] = $burndown['initial-minutes'] / 60;

        $haveToday = false;
        if ($todayKey >= $startKey && $todayKey <= $drawKey) {
            $haveToday = true;
            $todaySeries = array_fill(0, $increments, null);
        }

        // Iterate over all increments and get data

        $labels = [];
        $burned = 0;
        $dayKeys = [];
        foreach ($burndown['days'] as $dayKey => $day) {
            $labels[] = $day['label'];
            $dayKeys[] = $dayKey;

            $seriesStartKey = "ideal-{$dayKey}";
            if (!array_key_exists($seriesStartKey, $series)) {
                $series[$seriesStartKey] = [];
            }

            // Fill series with nulls
            $series[$seriesStartKey] = array_fill(0, $increments, null);

            // Add ideal data
            $dayIndex = $day['index'];
            $series[$seriesStartKey][$dayIndex-1] = $day['ideal']['total-minutes'] / 60;
            $series[$seriesStartKey][$dayIndex] = $day['ideal']['end-minutes'] / 60;

            // Add burned-down time
            $burned += $day['burned-down'];
            $burnedTo = ($day['ceiling-minutes'] - $burned) / 60;
            if ($dayKey < $todayKey) {
                $burnSeries[$dayIndex] = $burnedTo;
            } else if ($dayKey == $todayKey) {
                $todaySeries[$dayIndex-1] = $burnSeries[$dayIndex-1];
                $todaySeries[$dayIndex] = $burnedTo;
            }
        }

        $series['burndown'] = $burnSeries;
        if ($haveToday) {
            $series['today'] = $todaySeries;
        }

        $dayInterval = Console::interval('1d');
        $endDate = Console::time($burndown['enddate']);
        $endDate->add($dayInterval);

        //$trailKey = $endDate->format('Ymd');
        $labels[] = $endDate->format('l');

        $skeys = array_keys($series);

        // Perform calculations

        $totals = array_fill_keys($skeys, 0);
        $highs = array_fill_keys($skeys, 0);
        $lows = array_fill_keys($skeys, 0);
        foreach ($series as $seriesKey => $seriesData) {
            $total = array_sum($seriesData);
            $totals[$seriesKey] = is_numeric($total) ? round($total, 2) : 0;

            $max = max($seriesData);
            $highs[$seriesKey] = is_numeric($max) ? round($max, 2) : 0;

            $min = min($seriesData);
            $lows[$seriesKey] = is_numeric($min) ? round($min, 2) : 0;
        }

        // Build Output

        $this->setData('timezone', Console::utcTZ()->getName());
        $this->setData('grid', $increments);
        $this->setData('series', $skeys);

        $this->setData('graph', [
            'labels' => $labels
        ]);

        $output = [];
        foreach ($series as $seriesKey => $seriesData) {
            $output[$seriesKey] = [
                'name' => $seriesKey,
                'data' => $seriesData,
                'total' => $totals[$seriesKey],
                'high' => $highs[$seriesKey],
                'low' => $lows[$seriesKey],
                'average' => round($totals[$seriesKey] / $increments, 2)
            ];
        }

        $this->setData('data', $output);

        // Attach spike events

        $this->setData('events', [
            'keys' => $dayKeys,
            'series' => $burndown['events']
        ]);

        // Prepare the sprint progress for each workers
        $this->setData('workers-progress', $this->computeWorkersProgress($burndown));

        $this->render();
    }

    /**
     * Reset stored data for current week.
     */
    public function reset() {
        $this->deliveryMethod(DELIVERY_METHOD_JSON);
        $this->deliveryType(DELIVERY_TYPE_BOOL);

        // Check if we're current
        $refreshingMutexKey = 'teamwork.burndown.refreshing';
        $refreshing = Gdn::cache()->get($refreshingMutexKey);
        if (!$refreshing) {
            // Wait 300 seconds
            Gdn::cache()->store($refreshingMutexKey, true, [
                Gdn_Cache::FEATURE_EXPIRY => 300
            ]);

            // Refresh the cache
            $burndown = Teamwork::getBurndown(null, true);
            $this->setData('burndown', $burndown);

            // Shortcircuit refresh wait if successful completion
            Gdn::cache()->remove($refreshingMutexKey);
        }

        $this->render();
    }

    /**
     * Prepare the sprint progress for each workers.
     *
     * @param array $burndown The array representing the burndown
     * @return array An array of sprint progress for each workers
     */
    private function computeWorkersProgress($burndown) {
        // Get worker whitelist.
        // We need to do this again here to eliminate folks who slipped in on joint tasks.
        $validWorkers = explode(',', c('Teamwork.Workers'));

        // Attach worker events
        $workers = [];
        foreach ($burndown['workers'] as $workerID => $worker) {
            // Skip workers not in our whitelist.
            if (count($validWorkers) && !in_array($workerID, $validWorkers)) {
                continue;
            }

            $worker['estimated'] = round($worker['estimated-minutes'] / 60,1);
            $worker['completed'] = round($worker['completed-minutes'] / 60,1);

            $worker['estimated-text'] = $worker['estimated'] == 1 ? 'hour' : 'hours';
            $worker['completed-text'] = $worker['completed'] == 1 ? 'hour' : 'hours';
            $worker['ratio'] = round(($worker['completed'] / $worker['estimated']) * 100,0);
            $workers[] = $worker;
        }
        return $workers;
    }

}
