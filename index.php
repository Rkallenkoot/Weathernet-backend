<?php
require 'Slim/Slim.php';
include_once 'Connection.php';

\Slim\Slim::registerAutoloader();

$app = new \Slim\Slim();


// GET route
$app->get(
    '/',
    function () {
        echo "De volgende params kunnen worden gebruikt: <br>
        <table>
        <tr><th>Params</th><th>Description</th></tr>

        <tr><td><b> /station/:stationnumber </b></td><td> Alle info van dat stationnummer </td></tr>

        <tr><td><b> /station/all </b></td><td> Alle info van alle stations </td></tr>

        <tr><td><b> /..... </b></td><td> More soon </td></tr>

        <tr><td><b> /moscow </b></td><td> First query requirement: The measurements around Moscow(200km radius, from the centre of Moscow. Moscow local time).<br> 
And only if the temperature is higher than 18 degrees celsius (query, max response time: 2 minutes) </td></tr>

        <tr><td><b> /top10 </b></td><td> Second query requirement: With every Friday  22:00 – 00:00 will this query be accessed<br>
Also about top 10 peak temperature in 24h per longitude, <br>
only for Moscow (indicate which country the data is from) (max response time: 10 seconds)<br>
This should be available from Monday till Saturday 6:00 ~ 8:00 AM Moscow localtime (GMT +3) </td></tr>

        <tr><td><b> /rainfall/:stationnumber </b></td><td> Third query requirement: Rainfall in the world of any weatherstation of the current day
(from the current time till 00:00, going back) </td></tr>
        ";
    }
);

$app->group('/station', function () use ($app) {
    $app->get(
        '/:station',
        function ($station) {
            $conn = Connection::getInstance();

            if($station == 'all'){
                $statement = $conn->db->prepare("SELECT * FROM stations");
                $statement->execute();
            }else {
                $statement = $conn->db->prepare("SELECT * FROM stations WHERE stn = :stn");
                $statement->execute(array(':stn' => "$station"));
            }
            $results = $statement->fetchAll(PDO::FETCH_ASSOC);

            $json = json_encode($results);
            echo $json;
        }
    );

});

$app->group('/measurement', function () use ($app) {
    //moet nog
});



/*
First: 
The measurements around Moscow(200km radius, from the centre of Moscow. Moscow local time). 
And only if the temperature is higher than 18 degrees celsius (query, max response time: 2 minutes)
*/
$app->get(
    '/moscow',
    function(){
        $conn = Connection::getInstance();
        $statement = $conn->db->prepare("SELECT stn, latitude, longitude FROM stations");
        $stmt = $conn->db->prepare("SELECT latitude, longitude FROM stations where name = 'MOSKVA'");
        
        $statement->execute();
        $stmt->execute();
        
        $allStations = $statement->fetchAll();
        $moskvaStation = $stmt->fetchAll();

        $stns = [];
        foreach($allStations as $station){
            $afstand = distance($moskvaStation[0]['latitude'], $moskvaStation[0]['longitude'],$station['latitude'],$station['longitude']);
            if($afstand <= 200){
                $stns[] = $station['stn'];
            }
        }

        $stationnummers = implode(",",$stns);

        $statement2 = $conn->db->prepare("SELECT stn, temp FROM measurements  WHERE stn in ($stationnummers) AND temp > 10");
        $statement2->execute();
        $statement2->execute();
        $results2 = $statement2->fetchALL();

        $json = json_encode($results2);
        echo $json;
    }
);

/*
Second:
With every Friday  22:00 – 00:00 will this query be accessed
Also about top 10 peak temperature in 24h per longitude, 
only for Moscow (indicate which country the data is from) (max response time: 10 seconds)
This should be available from Monday till Saturday 6:00 ~ 8:00 AM Moscow localtime (GMT +3)
*/
$app->get(
    '/top10',
    function(){
        $conn = Connection::getInstance();
        $statement = $conn->db->prepare("
            SELECT s.country, m.temp, s.name, s.country, s.longitude, m.date, m.time 
            FROM measurements AS m
            JOIN stations AS s ON m.stn = s.stn
            WHERE s.longitude LIKE CONCAT (
                (SELECT TRUNCATE(longitude,0) 
                FROM stations
                WHERE name = 'MOSKVA'
                ),'%'
            )
            AND date >= now() - INTERVAL 1 DAY
            group by s.country
            ORDER BY temp DESC 
            LIMIT 10");
        $statement->execute();
        $results = $statement->fetchAll(PDO::FETCH_ASSOC);
        
        $json = json_encode($results);
        echo $json;
    }
);

/*
Third:
Rainfall in the world of any weatherstation of the current day
(from the current time till 00:00, going back)
*/
$app->get(
    '/rainfall/:station',
    function ($station) {
        $conn = Connection::getInstance();
        $statement = $conn->db->prepare("SELECT prcp FROM measurements WHERE stn = :stn AND date = :date");
        $statement->execute( array(':stn' => "$station", ':date' => date("Y-m-d")) );
        $results = $statement->fetchAll(PDO::FETCH_ASSOC);
        
        $json = json_encode($results);
        echo $json;
    }
);



function distance($lat1, $lon1, $lat2, $lon2) {

    $pi80 = M_PI / 180;
    $lat1 *= $pi80;
    $lon1 *= $pi80;
    $lat2 *= $pi80;
    $lon2 *= $pi80;

    $r = 6372.797; // mean radius of Earth in km
    $dlat = $lat2 - $lat1;
    $dlon = $lon2 - $lon1;
    $a = sin($dlat / 2) * sin($dlat / 2) + cos($lat1) * cos($lat2) * sin($dlon / 2) * sin($dlon / 2);
    $c = 2 * atan2(sqrt($a), sqrt(1 - $a));
    $km = $r * $c;

    //echo '<br/>'.$km;
    return $km;
}






$app->run();