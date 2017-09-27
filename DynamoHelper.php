<?php
// this class serves to simplify querying the DynamoDB database.
use Aws\DynamoDb\Exception\DynamoDbException;
use Aws\DynamoDb\DynamoDbClient;
use Aws\DynamoDb\Marshaler;
require '/home/alexei/vendor/autoload.php';

class DynamoHelper
{
    /**
     * Adds DynamoDB formatted items to the $params array. This array is later used within this class to simplify forming various DynamoDB queries.
     * @param  [array] $_params   - Initial $params array.
     * @param  [string] $keyName  - Name of the key to add.
     * @param  [string] $keyType  - Type of the key to add.
     * @param  [string] $keyValue - Value to store under the specified key.
     * @return [array]  $_params  - Initial $params array with added items.
     */
    function paramsAdd($_params, $keyName, $keyType, $keyValue)
    {
        $_params[$keyName] = [$keyType=>$keyValue];
        return $_params;
    }

    /**
     * Gets an item from DynamoDB that corresponds to the key provided.
     * @param  [array] $_params    - $params array to specify the item to get.
     * @param  [string] $tableName - Name of the table to get the item from.
     * @return [array] $result     - DynamoDB query result.
     */
    function getItem($_params, $tableName)
    {
        global $client;
        $result = $client->getItem([
            'ConsistentRead' => true,
            'Key' => $_params,
            'TableName' => $tableName,
        ]);
        return $result;
    }


    /**
     * Adds an item with specified properties to a table in the DB.
     * @param  [array] $_params    - $params array to specify the item to put.
     * @param  [string] $tableName - Name of the table to put the item into.
     * @return [array] $result     - DynamoDB query result.
     */
    function putItem($_params, $tableName)
    {
        global $client;
        $result = $client->putItem([
            'Item' => $_params,
            'TableName' => $tableName,
            ]);
        return $result;
    }

    /**
     * Counts the Items in array from getItem. If the item does not exist, the array should be empty.
     * @param  [array] $array - array to check
     * @return [bool]         - TRUE if array is populated. FALSE if empty.
     */
    function itemExists($array)
    {
        //echo count($_result['Item']);
        if(count($array['Item']) == 0){
            return FALSE;
        }
            else {
            return TRUE;
        }
    }

    /**
     * Gets salt from the database.
     * @return [string] $salt - Salt used for encrypting passwords.
     */
    function getSalt()
    {
        global $client;
        $marshaler = new Marshaler();
        $marsh = $marshaler->marshalJson('
            {
                ":slt":"salt"
            }
        ');

        try{
            $result = $client->query([
                'ConsistentRead' => true,
                'KeyConditionExpression' => '#key =:slt',
                'ExpressionAttributeNames' => ['#key' => 'key'],
                'ExpressionAttributeValues'=>$marsh,
                //'Select' => 'SPECIFIC_ATTRIBUTES',
                //'AttributesToGet' => ['value'],
                'TableName' => 'conf',
            ]);
        }
        catch (DynamoDbException $e)
        {
            echo $e->getAwsErrorMessage();
            die();
        }
        $salt = $result['Items'][0]['value']['S'];
        return $salt;
    }

    /**
     * Returns all Items in a table. Used only for debugging.
     * @param  [string] $_tableName - Name of the table to scan.
     * @return [array]               - DynamoDB query result.
     */
    function scanTable($_tableName)
    {
        global $client;
        $scan_response = $client->scan([
            'TableName' => $_tableName
        ]);
        return $scan_response;
    }

    /**
     * Gets a the oldest game with 'pending' status.
     * @return [array] - DynamoDB query result.
     */
    function getPending()
    {
        global $client;
        $marshaler = new Marshaler();

        $marsh = $marshaler->marshalJson('
            {
                ":status": "pending"
            }
        ');

        $result = $client->query([
            'TableName' => 'game',
            'IndexName' => 'status_datetime',
            'KeyConditionExpression' => '#st = :status',
            'ExpressionAttributeNames'=> [ '#st' => 'status' ],
            'ExpressionAttributeValues'=>$marsh,
            'Limit' => 1,
        ]);

        return $result;
    }
}
?>
