<?php
use Aws\DynamoDb\Exception\DynamoDbException;
use Aws\DynamoDb\DynamoDbClient;
//include './DynamoHelper.php';
require '/home/alexei/vendor/autoload.php';
//disables error reporting for non-existent array indices (decreases the notice output when waiting for opponent)
error_reporting(E_ERROR | E_PARSE);

class GameManager
{
    function __construct()
    {
        $board = array();
    }

    /**
     * Checks if there is a game with 'pending' status and joins it, creates new if there is none. Calls the function to
     * launch a game session after done.
     * @param [DynamoHelper object] $helper passes a DynamoHelper object designed to simplify querying the DB.
     */
    function joinBattle($helper)
    {
        $game = $helper->getPending();
        if($game['Count'] == 0){
            echo "There are no pending games. Creating...\n";
            $gameID = $this->createGame($helper);
            $owner = TRUE;
            $_SESSION['marker'] = 'X';
            }
        else {
            $gameData;
            echo "Joining game...";
            $gameID = $game['Items'][0]['gameID']['S'];
            $this->startGame($_SESSION['userName'], 'active', $gameID);
            $owner = FALSE;
            $_SESSION['marker'] = 'O';
        }
        $this->matchMake($helper, $gameID, $owner);
    }

    /**
     * Sets up the game session so that it is ready for playing and launches the game loop.
     * @param [DynamoHelper object] $helper passes a DynamoHelper object designed to simplify querying the DB.
     * @param [string] $gameID      - ID of the game to set up.
     * @param [type] $owner         - Name of the player who created the game.
     */
    private function matchMake($helper, $gameID, $owner)
    {
        $winner;
        $opponentName;
        $turn;
        $round = 1;
        if ($owner == TRUE)
        {
            echo 'Waiting for opponent' . PHP_EOL;
            while(empty($opponentName))
            {
                //echo 'Looking for game ID: ' . $gameID;
                $params = $helper->paramsAdd($params, 'gameID', 'S', $gameID);
                $opponentName = $helper->getItem($params, 'game')['Item']['opponent']['S'];
                sleep(1);
            }
            echo 'Opponent ' .$opponentName . ' connected' . PHP_EOL;
            $_SESSION['opponent'] = $opponentName;
        }
        else
        {
            echo 'Looking for game..' . PHP_EOL;
            $params = $helper->paramsAdd($params, 'gameID', 'S', $gameID);
            $ownerName = $helper->getItem($params, 'game')['Item']['owner']['S'];
            echo 'Joined game ' . $gameID . ' created by player: ' . $ownerName;
            $_SESSION['opponent'] = $ownerName;
        }
        $this->gameLoop($gameID);
    }

    /**
     * This is the main loop where players can make moves, and see the gameboard. Initiates scanning the gameboard to check winning conditions.
     * Runs until no winner is determined or there are no more possible moves.
     * @param [string] $gameID - ID of the game.
     */
    private function gameLoop($gameID)
    {
            while (TRUE) {
                $madeMove = FALSE;
                $gameboard = $this->getBoard($gameID);
                $this->printBoard($gameboard);

                while ($turn != $_SESSION['userName'] && $round < 10 && empty($winner)){
                    $gameState = $this->getState($gameID);
                    $turn = $gameState['Item']['turn']['S'];
                    $round = $gameState['Item']['round']['N'];
                    $winner = $gameState['Item']['winner']['S'];
                    sleep(1);
                }

                passthru('clear');
                echo "Round " . $round . PHP_EOL;
                $gameboard = $this->getBoard($gameID);
                $this->printBoard($gameboard);

                //checks before the player's move
                if ($round >= 5){
                    $winningMarker = $this->checkWinningCondition($gameboard);
                    if(!empty($winningMarker)){
                        if ($winningMarker == $_SESSION['marker']){
                            $this->SetWinner($_SESSION['userName'], $gameID);
                            $winner = $_SESSION['userName'];
                        }
                        else {
                            $this->SetWinner($_SESSION['opponent'], $gameID);
                            $winner = $_SESSION['opponent'];
                        }
                        break;
                    }
                }
                //10 is too many markers to fit on the gameboard
                if ($round > 9){
                    $this->SetWinner('result: tie', $gameID);
                    $this->announceWin('tie');
                    break;
                }

                while (!$madeMove){
                    echo "Place your marker." . PHP_EOL . "Row (a, b, c): " . PHP_EOL;
                    $row = readline();
                    echo PHP_EOL . "Column (1,2,3): " . PHP_EOL;
                    $col = readline();
                    $madeMove = $this->placeMarker($gameID, $row, $col, $_SESSION['marker'], $_SESSION['userName'], $_SESSION['opponent'], $round);
                }

                //checks after the player's move
                if ($round >= 5){
                    //echo "Checking after turn.";
                    $gameboard = $this->getBoard($gameID);
                    $winningMarker = $this->checkWinningCondition($gameboard);
                    if(!empty($winningMarker)){
                        //echo "WINNING MARKER = " . $winningMarker;
                        if ($winningMarker == $_SESSION['marker']){
                            $this->SetWinner($_SESSION['userName'], $gameID);
                            $winner = $_SESSION['userName'];
                        }
                        else {
                            $this->SetWinner($_SESSION['opponent'], $gameID);
                            $winner = $_SESSION['opponent'];
                        }
                        break;
                    }
                }
                $turn = $opponentName;
            }
            //calls result announcer
            $this->announceWin($winner);
        }

    /**
    * Checks who won the game, and announces the result of the game.
    * @param [string] $winner - name of the player who won the game. Empty if it's a tie.
    */
    private function announceWin($winner)
    {
        if ($winner == $_SESSION['userName'])
        {
            echo "You won!" . PHP_EOL;
        }
        elseif (!empty($winner)) {
            echo "You lost." . PHP_EOL;
        }
        elseif (empty($winner)) {
            echo "It was a tie" . PHP_EOL;
        }
    }



    /**
    * This function checks if there is a winning layout on the gameboard
    * @param [array] $gameboard - an array of occupied fields and their values
    * @return [string]          - the winning marker (X,O) if there is. Returns null if there is no winning layout.
    */
    private function checkWinningCondition ($gameboard){
        //check vertical
        for ($col = 1; $col < 4; $col++){
            if ($gameboard['a'.$col] == $gameboard['b'.$col] && $gameboard['a'.$col] == $gameboard['c'.$col] && !empty($gameboard['a'.$col])){
                    return $gameboard['a'.$col];
                }
        }
        //check horizontal
        $row = 'a';
        for ($i = 0; $i < 3; $i++){
            if(($gameboard[$row . '1'] == $gameboard[$row . '2']) && ($gameboard[$row . '1'] == $gameboard[$row . '3']) && !empty($gameboard[$row . '1'])){
                return $gameboard[$row . '1'];
                if ($i == 0){
                    $row = 'b';
                }
                if ($i == 1){
                    $row = 'c';
                }
            }
        }
        //check diagonal
        if (((($gameboard['a1'] == $gameboard['b2']) && ($gameboard['a1'] == $gameboard['c3'])) || (($gameboard['a3'] == $gameboard['b2']) && ($gameboard['a3'] == $gameboard['c1']))) && !empty($gameboard['b2'])){
            return $gameboard['b2'];
        }
        return null;
    }

    /**
    * Accesses the database game entry corresponding to the ID provided.
    * @param [string] $gameID - ID of the game we want to get the state of.
    * @return $state          - gamestate including whos turn it is, current round, and winner if one is determined.
    */
    private function getState($gameID){
        global $client;
        $state = $client->getItem([
            'ConsistentRead' => true,
            'Key' => [
                'gameID' => [
                    'S' => $gameID,
                ],
            ],
            'ProjectionExpression' => "turn, round, winner",
            'TableName' => 'game',
        ]);
        return $state;
    }

    /**
     * Accesses the database game entry corresponding to the ID provided.
     * @param [string] $gameID - ID of the game we want to get the state of.
     * @return [array] $gameboard - an array of occupied fields and their values
     */
    private function getBoard($gameID)
    {
        global $client;
        //$gameboard = array();
        $DBboard = $client->getItem([
            'ConsistentRead' => true,
            'Key' => [
                'gameID' => [
                    'S' => $gameID,
                ],
            ],
            'ProjectionExpression' => "a1, a2, a3, b1, b2, b3, c1, c2, c3",
            'TableName' => 'game',
        ])['Item'];

        foreach($DBboard as $key => $field)
            {
                $gameboard[$key] = $field['S'];
            }
        ksort($gameboard);
        return $gameboard;
    }

    /**
     * Outputs the gameboard to console
     * @param an array of occupied fields and their values
     * @return nothing
     */
    private function printBoard($gameboard)
    {
        $row = 'a';
        echo PHP_EOL;
        $k = 0;
        $i = 1;
        while($k < 9){

            if ($row == 'a' && $i == 4){
                $i = 1;
                $row = 'b';
                echo PHP_EOL;
            }
            if ($row == 'b' && $i == 4){
                $i = 1;
                $row = 'c';
                echo PHP_EOL;
            }
            $index = ($row . $i);
            if (!empty($gameboard[$index]))
            {
                echo ($gameboard[$index]);
            }
            else {
                echo " ";
            }

            $k++;
            $i++;
        }
        //passthru('clear');
        echo PHP_EOL;
    }

    /**
     * Updates a game entry in the DB.
     * Attempts to place a game marker at the specified field, set turn to opponent and increment game round number.
     * Query will only succeed if the game entry meets certain expecations:
     * 1) The field is not occupied to prevent overwriting another turn,
     * 2) The round corresponds to the current game round to prevent double marking
     * 3) The turn corresponds to player's name to prevent placing marker out of one's turn.
     * @param [string] $gameID       ID of the game entry we want to modify.
     * @param [string] $row          Gamefield row to place marker; provided by player.
     * @param [string] $col          Gamefield column to place marker; provided by player.
     * @param [string] $marker       Player's marker (X,O)
     * @param [string] $userName     Player who initiated the function call.
     * @param [string] $opponentName Player's opponent's name.
     * @param [int] $round           The number of the current round.
     * @return True if the query has succeed; false if it was unsuccessful.
     */
    private function placeMarker($gameID, $row, $col, $marker, $userName, $opponentName, $round)
    {
        global $client;
        $fieldName = $row . $col;

        try{
            $result = $client->updateItem([
                'TableName' => 'game',
                'Key' => [
                    'gameID' => [
                        'S' => $gameID,
                    ],
                ],
                'ConditionExpression' => 'attribute_not_exists(#field) and #turn = :currentPlayer and #round = :currentRound',

                'UpdateExpression' => 'SET #field = :value, #turn =:opponent, #round = :nextRound',

                "ExpressionAttributeNames" => [
                    '#field' => $fieldName,
                    '#turn' => 'turn',
                    '#round' => 'round',
                ],
                "ExpressionAttributeValues" => [
                    ':value' => ['S' => $marker,],
                    ':opponent' => ['S' => $opponentName],
                    ':currentRound' => ['N' => $round],
                    ':nextRound' => ['N' => $round + 1],
                    ':currentPlayer' => ['S' => $userName],
                ],
            ]);
        }
        catch(DynamoDbException $e)
            {
                echo PHP_EOL . "Invalid move";
                return FALSE;
            }
            return TRUE;
    }

    /**
    * Creates a new game entry in the DB. Creates and sets: unique timestamp based ID, 'pending' status, timestamp of game creation,
    * name of the player who created the game session, turn value, and round number.
c
    * @return the ID of the new game entry.
    */
    private function createGame($helper)
    {
        $newID = uniqid();
        $gameData = array();
        $gameData = $helper->paramsAdd($gameData, 'gameID', 'S', $newID);
        $gameData = $helper->paramsAdd($gameData, 'status', 'S', 'pending');
        $gameData = $helper->paramsAdd($gameData, 'datetime', 'S', time());
        $gameData = $helper->paramsAdd($gameData, 'owner', 'S', $_SESSION['userName']);
        $gameData = $helper->paramsAdd($gameData, 'turn', 'S', $_SESSION['userName']);
        $gameData = $helper->paramsAdd($gameData, 'round', 'N', '1');
        $result = $helper->putItem($gameData, 'game');
        return $newID;
    }

    /**
     * Updates the game entry in the database to reflect that an opponent has joined the game.
     * @param  [string] $opponentName Name of the player who joined.
     * @param  [string] $newStatus    New status of the game.
     * @param  [string] $gameID       ID of the game session entry to update.
     * @return                      Nothing.
     */
    private function startGame($opponentName, $newStatus, $gameID)
    {
        global $client;
        $result = $client->updateItem([
            'TableName' => 'game',
            'Key' => [
                'gameID' => [
                    'S' => $gameID,
                ],
            ],

            'UpdateExpression' => 'SET #status = :newStatus ,#opponent = :opponentName',
            "ExpressionAttributeNames" => [
                '#status' => 'status',
                '#opponent' => 'opponent'],
            "ExpressionAttributeValues" => [
                ':newStatus' => ['S' => $newStatus,],
                ':opponentName' => ['S' => $opponentName,]
            ]
        ]);
    }

    /**
     * Sets the winner attribute of a given game session entry to the player's name.
     * @param [string] $playerName Name of the player to set as winner.
     * @param [string] $gameID     ID of the game entry to update.
     * @return                     Gives nothing back.
     */
    private function SetWinner($playerName, $gameID)
    {
        global $client;

        $result = $client->updateItem([
            'TableName' => 'game',
            'Key' => [
                'gameID' => [
                    'S' => $gameID,
                ],
            ],

            'UpdateExpression' => 'SET #winnerattribute = :winnerame, #status = :newStatus',

            "ExpressionAttributeNames" => [
                '#winnerattribute' => 'winner',
                '#status' => 'status',
            ],
            "ExpressionAttributeValues" => [
                ':winnerame' => ['S' => $playerName,],
                ':newStatus' => ['S' => 'closed',],
            ],
        ]);
    }
}

 ?>
