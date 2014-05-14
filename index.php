<?php
///////////////////////////////////////////////////////////////////////////////////////////////////////////////////
///////////////////////////////////////////////////// SETTINGS ////////////////////////////////////////////////////
///////////////////////////////////////////////////////////////////////////////////////////////////////////////////
define('JIRA_USER', '');
define('JIRA_PASS', '');
define('JIRA_SUBDOMAIN', '');
define('JIRA_PROJECT', '');

define('PROJECT_NAME', '');

define('PROJECT_DATE_FORMAT', 'd M Y');
define('PROJECT_LOCALE', 'fr_FR');
define('PROJECT_TIMEZONE', 'Europe/Paris');

///////////////////////////////////////////////////////////////////////////////////////////////////////////////////
/////////////////////////////////////////////////// DONT'T TOUCH //////////////////////////////////////////////////
///////////////////////////////////////////////////////////////////////////////////////////////////////////////////
define('ISSUES_QUERY', 'project = ' . JIRA_PROJECT . ' AND issuetype = Epic AND status != Closed');
define('URL_REST', 'https://' . JIRA_SUBDOMAIN . '.atlassian.net/rest');
define('URL_ISSUES', URL_REST . '/api/2/search?jql=' . urlencode(ISSUES_QUERY));
define('URL_EPICS', URL_REST . '/greenhopper/1.0/xboard/plan/backlog/epics?&rapidViewId=4');

require 'vendor/autoload.php';

date_default_timezone_set(PROJECT_TIMEZONE);
setlocale(LC_TIME, PROJECT_LOCALE);

try {
    $client = new GuzzleHttp\Client();
    $responseIssues = $client->get(URL_ISSUES, ['auth' => [JIRA_USER, JIRA_PASS]]);
    $responseEpics  = $client->get(URL_EPICS, ['auth' => [JIRA_USER, JIRA_PASS]]);

    $issues = $responseIssues->json();
    $epics  = $responseEpics->json();

    // Get due dates
    $duedates = [];
    foreach ($issues['issues'] as $issue) {
        $duedates[$issue['key']] = $issue['fields']['duedate']
            ? new \DateTime($issue['fields']['duedate'])
            : null;
    }

    // List epics
    $epicsCollection = new \ArrayObject;
    foreach ($epics['epics'] as $epic) {
        // Display open issue
        if ('Open' === $epic['status']['name']) {
            // Useful values
            $id = $epic['key'];

            // Create epic object
            $epicObject = new \stdClass;

            // Map common values
            $epicObject->id                    = $id;
            $epicObject->name                  = $epic['epicLabel'];
            $epicObject->dueDate               = isset($duedates[$id]) ? $duedates[$id] : null;
            $epicObject->viewableDueDate       = isset($duedates[$id]) ? $duedates[$id]->format(PROJECT_DATE_FORMAT) : '?';
            $epicObject->haveUnestimatedIssues = (bool)$epic['epicStats']['percentageUnestimated'];
            if ($epicObject->dueDate instanceof \DateTime) {
                $now                    = new \DateTime();
                $interval               = $now->diff($epicObject->dueDate);
                $epicObject->offsetDate = (int)$interval->format('%R%a');
            } else {
                $epicObject->offsetDate = 0;
            }

            // Map issues values
            $epicObject->issues          = new \stdClass;
            $epicObject->issues->total   = $epic['epicStats']['totalIssueCount'];
            $epicObject->issues->percent = $epic['epicStats']['totalIssueCount']
                ? round(((float)$epic['epicStats']['done'] / (float)$epic['epicStats']['totalIssueCount']) * 100)
                : 0;
            $epicObject->issues->done    = $epic['epicStats']['done'];
            $epicObject->issues->notDone = $epic['epicStats']['notDone'];

            // Map points values
            $epicObject->points = new \stdClass;
            $epicObject->points->total   = $epic['epicStats']['totalEstimate'];
            $epicObject->points->percent = $epic['epicStats']['percentageCompleted'];
            $epicObject->points->done    = $epic['epicStats']['doneEstimate'];
            $epicObject->points->notDone = $epic['epicStats']['notDoneEstimate'];

            // Add epic object to collection
            $epicsCollection->append($epicObject);

            // Clean memory
            unset($epicObject);
         }
    }
} catch (Exception $e) {
    exit('Unable to connect! (Auth?)' . PHP_EOL);
}
///////////////////////////////////////////////////////////////////////////////////////////////////////////////////
////////////////////////////////////////////////////// DISPLAY ////////////////////////////////////////////////////
///////////////////////////////////////////////////////////////////////////////////////////////////////////////////
?>
<!DOCTYPE html>
<!--[if lt IE 7]>      <html class="no-js lt-ie9 lt-ie8 lt-ie7"> <![endif]-->
<!--[if IE 7]>         <html class="no-js lt-ie9 lt-ie8"> <![endif]-->
<!--[if IE 8]>         <html class="no-js lt-ie9"> <![endif]-->
<!--[if gt IE 8]><!--> <html class="no-js"> <!--<![endif]-->
    <head>
        <meta charset="utf-8">
        <meta http-equiv="X-UA-Compatible" content="IE=edge">
        <title><?= PROJECT_NAME; ?> Parking Lot</title>
        <meta name="description" content="Parking lot">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <link href="assets/css/main.css" rel="stylesheet">

        <link rel="stylesheet" href="assets/css/main.css">
        <link rel="icon" href="assets/img/favicon.ico">
    </head>
    <body>
        <div class="container-fluid">
            <div class="row">
                <div class="col-md-12 intro">
                    <h1><?= PROJECT_NAME; ?> Parking Lot</h1>
                </div>
            </div>
            <div class="row">
                <?php foreach ($epicsCollection as $epic): ?>
                <div class="col-md-2 featureInner" title="<?= $epic->id; ?>">
                    <div class="featureSet <?php if ($epic->offsetDate): ?>danger<?php elseif ($epic->offsetDate >= 20): ?>warning<?php else: ?>normal<?php endif; ?>">
                        <div class="card">
                            <h2 class="name"><?= $epic->name; ?></h2>
                            <div class="nbStories"><?= $epic->issues->total; ?></div>
                        </div>
                        <div class="progress">
                            <div class="progress-bar progress-bar-success" role="progressbar" aria-valuenow="<?= $epic->points->percent; ?>" aria-valuemin="0" aria-valuemax="100" style="width: <?= $epic->points->percent; ?>%">
                                <span><?= $epic->points->percent; ?>%</span>
                            </div>
                        </div>
                        <div class="dueDate"><?php if ($epic->viewableDueDate): ?><?= $epic->viewableDueDate; ?><?php else: ?>Non déterminé<?php endif; ?></div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <script src="assets/js/main.js"></script>
    </body>
</html>