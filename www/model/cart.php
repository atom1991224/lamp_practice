<?php 
require_once MODEL_PATH . 'functions.php';
require_once MODEL_PATH . 'db.php';

function get_user_history($db, $user_id){
  $sql = '
    SELECT
      carts.created,
      carts.user_id,
      carts.item_id,
      carts.amount,
      histories.created,
      details.amount,
      details.item_id
    FROM
      carts
    INNER JOIN 
      histories
    ON
      carts.user_id = histories.user_id  
    INNER JOIN
      details
    ON
      histories.history_id = details.history_id
    INNER JOIN
      details
    ON
      carts.item_id = details.item_id  
      ';
    return fetch_query($db,$sql);  
}

function get_user_datails($db, $user_id){
  $sql = '
    SELECT
      items.price,
      details.price
    FROM
      items
    INNER JOIN
      details
    ON
      items.item_id = details.item_id  
      ';
}

//ユーザーIDを取得
function get_user_carts($db, $user_id){
  $sql = "
    SELECT
      items.item_id,
      items.name,
      items.price,
      items.stock,
      items.status,
      items.image,
      carts.cart_id,
      carts.user_id,
      carts.amount
    FROM
      carts
    JOIN
      items
    ON
      carts.item_id = items.item_id
    WHERE
      carts.user_id = {$user_id}
  ";
  return fetch_all_query($db, $sql);
}

//カートの中身を取得
function get_user_cart($db, $user_id, $item_id){
  $sql = "
    SELECT
      items.item_id,
      items.name,
      items.price,
      items.stock,
      items.status,
      items.image,
      carts.cart_id,
      carts.user_id,
      carts.amount
    FROM
      carts
    JOIN
      items
    ON
      carts.item_id = items.item_id
    WHERE
      carts.user_id = {$user_id}
    AND
      items.item_id = {$item_id}
  ";

  return fetch_query($db, $sql);

}
//カートに追加
function add_cart($db, $user_id, $item_id ) { 
  $cart = get_user_cart($db, $user_id, $item_id);
  if($cart === false){
    return insert_cart($db, $user_id, $item_id);
  }
  return update_cart_amount($db, $cart['cart_id'], $cart['amount'] + 1);
}

//カートの中身を１つ追加
function insert_cart($db, $user_id, $item_id, $amount = 1){
  $sql = "
    INSERT INTO
      carts(
        item_id,
        user_id,
        amount
      )
    VALUES (?, ?, ?)
  ";

  return execute_query($db, $sql, array($item_id, $user_id, $amount));
}

//購入数変更更新
function update_cart_amount($db, $cart_id, $amount){
  $sql = "
    UPDATE
      carts
    SET
      amount = ?
    WHERE
      cart_id = ?
    LIMIT 1
  ";
  
  return execute_query($db, $sql, array($amount, $cart_id));
}


// PDO、SQL文、$paramを利用してプリペアドステートメントを実行する
//function execute_query($db, $sql, $params = array()){
// try{
//  $stmt = $db->prepare($sql);
//  return $stmt->execute($params);
//  }catch(PDOException $e){
//  set_error('更新に失敗しました。');
//  }
//  return false;
//  }

//カートに入っている商品を削除
function delete_cart($db, $cart_id){
  $sql = "
    DELETE FROM
      carts
    WHERE
      cart_id = {$cart_id}
    LIMIT 1
  ";

  return execute_query($db, $sql);
}

//カートから購入する
function purchase_carts($db, $carts){
  if(validate_cart_purchase($carts) === false){
    return false;
  }
  foreach($carts as $cart){
    if(update_item_stock(
        $db, 
        $cart['item_id'], 
        $cart['stock'] - $cart['amount']
      ) === false){
      set_error($cart['name'] . 'の購入に失敗しました。');
    }
  }
  
  delete_user_carts($db, $carts[0]['user_id']);
}

//購入後カートをの中身を削除
function delete_user_carts($db, $user_id){
  $sql = "
    DELETE FROM
      carts
    WHERE
      user_id = {$user_id}
  ";

  execute_query($db, $sql);
}

//カートの中身の合計金額
function sum_carts($carts){
  $total_price = 0;
  foreach($carts as $cart){
    $total_price += $cart['price'] * $cart['amount'];
  }
  return $total_price;
}

//購入バリデーション
function validate_cart_purchase($carts){
  if(count($carts) === 0){
    set_error('カートに商品が入っていません。');
    return false;
  }
  foreach($carts as $cart){
    if(is_open($cart) === false){
      set_error($cart['name'] . 'は現在購入できません。');
    }
    if($cart['stock'] - $cart['amount'] < 0){
      set_error($cart['name'] . 'は在庫が足りません。購入可能数:' . $cart['stock']);
    }
  }
  if(has_error() === true){
    return false;
  }
  return true;
}

