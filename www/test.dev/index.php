<?php

    // так как метод апи единственный то представим что обратываем запрос вида /api/v1/price_updade.json
    
    header('Content-Type: application/json; charset=utf-8');
 
    $Api_Key = '202cb962ac59075b964b07152d234b70'; //ключ апи  md5('123')




    //обработка ошибок
    if(!isset($_POST['key']) && !isset($_POST['data'])){
        http_response_code(404);
        die(json_encode(['error' => 'not exist reguired params']));
        
    }



    if( $_POST['key'] !== $Api_Key ){
        http_response_code(401); 
        die(json_encode(['error' => 'access denied']));
    }

    if( !isJSON($_POST['data'])) {
        die(json_encode(['error' =>'data string must be json']));
    }

    $data = json_decode($_POST['data'],1); // получаем асоциативный массив из json 



    if(count($data) == 0){
        die(json_encode(['error' => 'not product']));
    }

    if(count($data) > 1000) {
        die(json_encode(['error' => '1000 products limit']));
    }
    // 

    try {
        $dbh = new PDO('mysql:host=mysql', 'root', 'password');

        $dbname = 'test';
        
        $dbname = "`".str_replace("`","``",$dbname)."`";
        $dbh->query("CREATE DATABASE IF NOT EXISTS $dbname"); //создаем базу если ее нет
        $dbh->query("use $dbname");

    } catch (PDOException $e) {
        print "Error!: " . $e->getMessage() . "<br/>";
        die();
    }




    $_table = 'product_prices';


    $sql = "
    CREATE TABLE IF NOT EXISTS `{$_table}` (
      `product_id` int NOT NULL,
      `region_id` tinyint NOT NULL,
      `price_purchase` float NOT NULL,
      `price_selling` float NOT NULL,
      `price_discount` float NOT NULL,
      UNIQUE KEY `product_id_region_id` (`product_id`,`region_id`),
      KEY `region_id_product_id` (`region_id`,`product_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3;
    ";
    $stm = $dbh->prepare($sql);
    $stm->execute();  // создаем таблицу если ее нет



    $product_update = 0;

    foreach($data as $product){
        
        foreach( $product['prices'] as $k => $price) {
            $region_id = $k;
            $sql = "INSERT INTO {$_table} (`product_id`, `region_id`, `price_purchase`, `price_selling`, `price_discount`) VALUES(?,?,?,?,?) ON DUPLICATE KEY UPDATE price_purchase=?, price_selling=?, price_discount=?";
            $stm = $dbh->prepare($sql);
            $stm->execute( [$product['product_id'],$region_id, $price['price_purchase'],  $price['price_selling'],  $price['price_discount'],$price['price_purchase'],  $price['price_selling'],  $price['price_discount'] ]);
        }
        $product_update++;
    }

    die(json_encode(array('Result' => 'ok', 'product_update' => $product_update)));



    function isJSON($string){
        return is_string($string) && is_array(json_decode($string, true)) && (json_last_error() == JSON_ERROR_NONE) ? true : false;
     }
?>