<?php

/*
|--------------------------------------------------------------------------
| Application Routes
|--------------------------------------------------------------------------
|
| Here is where you can register all of the routes for an application.
| It is a breeze. Simply tell Lumen the URIs it should respond to
| and give it the Closure to call when that URI is requested.
|
*/

$router->get('/', function () {
    return "Error: Wubba Lubba Dub Dub.";
});

// Jammer endpoints
$router->get('/jammers', 'Jammers@getJammers');
$router->get('/jammer/{id}', 'Jammers@getJammer');
$router->get('/jammer', 'Jammers@getJammer');
$router->put('/jammer/{name}', 'Jammers@createJammer');
$router->post('/jammer/{id}/{status}', 'Jammers@updateJammer');
$router->delete('/jammer/{id}', 'Jammers@deleteJammer');

$router->get('/todaysjammer/{date}', 'Jammers@getJammerByDate');
$router->get('/todaysjammer', 'Jammers@getJammerByDate');

$router->get('/randomjammer/{excludeId}', 'Jammers@getRandomJammer');
$router->get('/randomjammer', 'Jammers@getRandomJammer');

$router->get('/highscore', 'Jammers@getHighScoreJammer');

// Jam endpoints
$router->get('/jam/{id}', 'Jams@getJam');
$router->get('/jam/', 'Jams@getJam');
$router->put('/jam/{jammer_id}/{youtube_id}', 'Jams@createJam');
$router->post('/jam/{id}', 'Jams@updateJam');
$router->delete('/jam/{id}', 'Jams@deleteJam');

$router->get('/wildJam', 'Jams@wildcardJam');

$router->get('/historicJam/{date}', 'Jams@historicJam');
$router->get('/historicJam', 'Jams@historicJam');