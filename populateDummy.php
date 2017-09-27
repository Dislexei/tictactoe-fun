<?php

use Aws\DynamoDb\Exception\DynamoDbException;
use Aws\DynamoDb\DynamoDbClient;

require '/home/alexei/vendor/autoload.php';

include './DynamoHelper.php';
include './Includes/createClient.php';

date_default_timezone_set('UTC');
/**
 * This script adds some sample data to the database, including salt, and test users 'test1' and 'test2'
 */

$helper = new DynamoHelper();

//generates and puts salt into the DB
$bytes = random_bytes(8);
$salt = bin2hex($bytes);

$result = $client->putItem([
            'Item' => [
                'key' => ['S' => 'salt',],
                'value' =>['S' => $salt,],
            ],
            'TableName' => 'conf',
]);

$pass1 = md5('test1'.$salt);
$pass2 = md5('test2'.$salt);

$result = $client->putItem([
            'Item' => [
                'login' =>['S' => 'test1',],
                'pass'=>['S' => $pass1,],
            ],
            //'ReturnValues' => 'ALL_OLD',
            'TableName' => 'users',
]);

$result = $client->putItem([
            'Item' => [
                'login' =>['S' => 'test2',],
                'pass'=>['S' => $pass2,],
            ],
            //'ReturnValues' => 'ALL_OLD',
            'TableName' => 'users',
]);

echo "Dummy data population complete.\n";
?>
