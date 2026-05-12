<?php

function getOpsStatus($conn){

    $sql = "
        SELECT *
        FROM ops_status
        WHERE is_active = 1
        ORDER BY id DESC
    ";

    $result = mysqli_query($conn, $sql);

    if(!$result){
        return [];
    }

    return mysqli_fetch_all($result, MYSQLI_ASSOC);
}