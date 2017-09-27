<?php

/**
 * This script creates the DynamoDB tables for running the application
 */
use Aws\DynamoDb\Exception\DynamoDbException;
use Aws\DynamoDb\DynamoDbClient;

require '/home/alexei/vendor/autoload.php';
include './Includes/createClient.php';

date_default_timezone_set('UTC');

$dummyData = TRUE;

$params = [
    'AttributeDefinitions' => [
        [
            'AttributeName' => 'gameID',
            'AttributeType' => 'S',
        ],
        [
            'AttributeName' => 'status',
            'AttributeType' => 'S',
        ],
        [
            'AttributeName' => 'datetime',
            'AttributeType' => 'S',
        ],
    ],

    'KeySchema' => [
        [
            'AttributeName' => 'gameID',
            'KeyType'       => 'HASH',
        ],
    ],

    'GlobalSecondaryIndexes' => [
        [
            'IndexName' => 'status_datetime',
            'KeySchema' => [
                    [
                        'AttributeName' => 'status',
                        'KeyType' => 'HASH',
                    ],
                    [
                        'AttributeName' => 'datetime',
                        'KeyType' => 'RANGE',
                    ],
            ],
            'Projection' => [
                   'ProjectionType' => 'ALL',
            ],
            'ProvisionedThroughput' => [
                   'ReadCapacityUnits' => 5,
                   'WriteCapacityUnits' => 5,
            ],
        ],
    ],

    'ProvisionedThroughput' => [
        'ReadCapacityUnits'  => 5,
        'WriteCapacityUnits' => 5,
    ],
    'TableName' => 'game',
];
createTable($params);

$params = [
    'AttributeDefinitions' => [
        [
            'AttributeName' => 'key',
            'AttributeType' => 'S',
        ],
    ],

    'KeySchema' => [
        [
            'AttributeName' => 'key',
            'KeyType'       => 'HASH',
        ],
    ],
    'ProvisionedThroughput' => [
        'ReadCapacityUnits'  => 5,
        'WriteCapacityUnits' => 1,
    ],
    'TableName' => 'conf',
  ];
createTable($params);

$params = [
  'TableName' => 'users',
  'AttributeDefinitions' => [
      [
          'AttributeName' => 'login',
          'AttributeType' => 'S'
      ]

  ],

  'KeySchema' => [
      [
          'AttributeName' => 'login',
          'KeyType'       => 'HASH'
      ]
  ],
  'ProvisionedThroughput' => [
      'ReadCapacityUnits'  => 5,
      'WriteCapacityUnits' => 10
  ]
];
createTable($params);

$params = [
  'TableName' => 'games',
  'AttributeDefinitions' => [
      [
          'AttributeName' => 'id',
          'AttributeType' => 'N'
      ],
      [
          'AttributeName' => 'name',
          'AttributeType' => 'S'
      ]

  ],
  'KeySchema' => [
      [
          'AttributeName' => 'id',
          'KeyType'       => 'HASH'
      ],
      [
          'AttributeName' => 'name',
          'KeyType'       => 'RANGE'
      ]
  ],
  'ProvisionedThroughput' => [
      'ReadCapacityUnits'  => 10,
      'WriteCapacityUnits' => 20
  ]
];
createTable($params);

/**
 * Creates a table using DynamoHelper object.
 * @param  [array] $params - Specifies parameters for the table.
 * @return                 - Nothing.
 */
function createTable($params)
{
  global $client;

  try{
    $client->createTable($params);
    echo "Table " . $params['TableName'] . " created."."\n";
  }
 //deletes the table if already exists, or display the error message if the problem is something else
  catch (DynamoDbException $e)
  {
    if ($e->getAwsErrorCode()=='ResourceInUseException')
    {
        //echo $e->getAwsErrorMessage();
        echo "Table " . $params['TableName'] . ". Already exists. Deleting table.."."\n";
        $client->deleteTable([
          'TableName' => $params['TableName']
        ]);
        echo "Table " . $params['TableName'] . " deleted."."\n";
        createTable($params);
    }
    else {
              echo $e->getAwsErrorMessage();
              die();
    }
  }
}

echo "Installation successful.\n";
if ($dummyData){
    echo "Inserting dummy data.\n";
    include('./populateDummy.php');
}
?>
