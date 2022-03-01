<?php

    // так как метод апи единственный то представим что обратываем запрос вида /api/v1/price_update.json
    
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



    $product_region_price_add = 0;
    $product_region_price_update = 0;
    $product_update = 0;

    foreach($data as $product){
        $sql = '';
        foreach( $product['prices'] as $region_id => $price) {
            $stmt = $dbh->prepare("SELECT price_purchase, price_selling, price_discount  FROM {$_table} WHERE product_id = ? and region_id = ?");
            $stmt->execute([$product['product_id'], $region_id]);
            $old_price = $stmt->fetch(PDO::FETCH_ASSOC); // получаем строку в ассоциативном массиве
            if($old_price === false)
            {
                $sql .= "INSERT INTO {$_table} (`product_id`, `region_id`, `price_purchase`, `price_selling`, `price_discount`) VALUES('{$product['product_id']}','{$region_id}', '{$price['price_purchase']}', '{$price['price_discount']}', '{$price['price_selling']}'); "; 
                $product_region_price_add++;
            }
            else if($old_price != $price) // сраниваем массивы
            {
                $sql .= "UPDATE `product_prices` SET `price_purchase` = '{$price['price_purchase']}', `price_selling` = '{$price['price_selling']}', `price_discount` = '{$price['price_discount']}'  WHERE `product_id` = '{$product['product_id']}' AND `region_id` = '{$region_id}' LIMIT 1; ";
                $product_region_price_update++;
            }

        }
        if($sql !== '')
        {
            $stm = $dbh->prepare($sql);
            $stm->execute();
            $product_update++;
        }
    }

    die(json_encode(['Result' => 'ok', 'product_update' => $product_update, 'product_region_price_add' => $product_region_price_add, 'product_region_price_update' => $product_region_price_update]));



    function isJSON($string){
        return is_string($string) && is_array(json_decode($string, true)) && (json_last_error() == JSON_ERROR_NONE) ? true : false;
     }
?>