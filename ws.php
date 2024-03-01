<?php
session_name("pwg_id");
session_start();

define('IN_COPPERMINE', true);
define('THUMBNAILS_PHP', true);
define('INDEX_PHP', true);

$param_method = $_REQUEST['method'] ?? "";
$param_cat_id = $_REQUEST['cat_id'] ?? "";
$param_image_id = $_REQUEST['image_id'] ?? "";
$param_username = $_REQUEST['username'] ?? "";
$param_password = $_REQUEST['password'] ?? "";
$cookie_pwg_id = $_COOKIE["pwg_id"] ?? "";

require('include/init.inc.php');
include('include/archive.php');

function pwg_init() {
    global $CONFIG,$param_method,$param_cat_id,$param_image_id,$param_username,$param_password,$cookie_pwg_id;
    $_SESSION["api_hostname"] = $CONFIG["site_url"];

    // $sql = "SELECT b.group_name as group_name FROM {$CONFIG['TABLE_USERS']} as a,{$CONFIG['TABLE_USERGROUPS']} as b WHERE a.user_group = b.group_id and a.user_name = '".$param_username."' LIMIT 1";
    // $result = cpg_db_query($sql);
    // $CONFIG["user_role"] = $result->fetchAssoc(true)["group_name"];

    // echo var_dump($CONFIG);
}

function pwg_session_login() {
    global $CONFIG,$param_method,$param_cat_id,$param_image_id,$param_username,$param_password,$cookie_pwg_id;

    $pwg_session_login = ["stat" => "ok","result" => true];

    pwg_init();

    require 'include/passwordhash.inc.php';
    $sql = "SELECT user_password, user_password_salt, user_password_hash_algorithm, user_password_iterations FROM {$CONFIG['TABLE_USERS']} WHERE user_name = '".$param_username."' LIMIT 1";
    $result = cpg_db_query($sql);
    $password_params = $result->fetchAssoc(true);
    if(cpg_password_validate($param_password, $password_params)) {
        $pwg_session_login["result"] = true;
        $_SESSION["username"] = $param_username;
        $_SESSION["user_role"] = $CONFIG["user_role"];
        echo json_encode($pwg_session_login);
    } else {
        echo '{"stat":"fail","err":999,"message":"Invalid username\/password"}';
    }
}

function pwg_session_logout() {
    global $CONFIG,$param_method,$param_cat_id,$param_image_id,$param_username,$param_password,$cookie_pwg_id;

    $pwg_session_logout = ["stat" => "ok","result" => true];

    echo json_encode($pwg_session_logout);
}

function pwg_session_getStatus() {
    global $CONFIG,$param_method,$param_cat_id,$param_image_id,$param_username,$param_password,$cookie_pwg_id;

    $pwg_session_getStatus = [
        "stat" => "ok",
        "result" => [
            "username" => "guest",
            "status" => "guest",
            "theme" => "default",
            "language" => $CONFIG["default_lang"],
            "pwg_token" => "",
            "charset" => "utf-8",
            "current_datetime" => "",
            "version" => "14.2.0",
            "save_visits" => false,
            "available_sizes" => ["square","thumb","2small","xsmall","small","medium","large","xlarge","xxlarge"],
            "upload_file_types" => "jpg,jpeg,png,gif,webp",
            "upload_form_chunk_size" => 500
        ]
    ];

    if(!$_SESSION["username"]){     
        echo json_encode($pwg_session_getStatus);
    } else {
        $pwg_session_getStatus["result"]["username"] = $_SESSION["username"];
        $pwg_session_getStatus["result"]["status"] = "normal";
        echo json_encode($pwg_session_getStatus);
    }
    //echo $pwg_session_getStatus;
}

function pwg_reflection_getMethodList() {
    global $CONFIG,$param_method,$param_cat_id,$param_image_id,$param_username,$param_password,$cookie_pwg_id;

    $reflection_getMethodList = [
        "stat" => "ok",
        "result" => [
            "methods" => [
                "pwg.categories.getImages",
                "pwg.categories.getList",
                "pwg.images.getInfo",
                "pwg.session.getStatus",
                "pwg.session.login",
                "pwg.session.logout",
                "reflection.getMethodList"
            ]
        ]
    ];

    echo json_encode($reflection_getMethodList);
    //echo $reflection_getMethodList;
}

function pwg_categories_getList() {
    global $CONFIG,$param_method,$param_cat_id,$param_image_id,$param_username,$param_password,$cookie_pwg_id;

    $pwg_categories_getList = [
        "stat" => "ok",
        "result" => [
            "categories" => []
        ]
    ];

    $cat_list = [];
    if($param_cat_id && $param_cat_id != 0) {
        $top_level = cpg_db_fetch_rowset(
            cpg_db_query("SELECT cid, name, pos FROM {$CONFIG['TABLE_CATEGORIES']} WHERE cid = $param_cat_id"));
        // アルバム用
        if($top_level) {
            $cat_id = $top_level[0]['cid'];
            $cat_name = $top_level[0]['name'];
            $pos_num = $top_level[0]['pos'];

            $cnt_result_alb = cpg_db_fetch_rowset(
                cpg_db_query("select count(aid) as alb_count from {$CONFIG['TABLE_ALBUMS']} as alb where alb.category = $cat_id"));
            $alb_count = $cnt_result_alb[0]['alb_count'];

            $cnt_result = cpg_db_fetch_rowset(
                cpg_db_query("SELECT count(pic.pid) as pic_count FROM {$CONFIG['TABLE_PICTURES']} as pic WHERE pic.aid in (select aid from {$CONFIG['TABLE_ALBUMS']} as alb where alb.category = $cat_id)"));
            $pic_count = $cnt_result[0]['pic_count'];

            $cnt_result_th = cpg_db_fetch_rowset(
                cpg_db_query("SELECT pid, filepath, filename FROM {$CONFIG['TABLE_PICTURES']} as pic WHERE pic.aid in (select alb.aid from {$CONFIG['TABLE_ALBUMS']} as alb where alb.category = $cat_id)"));

            $path = $cnt_result_th[0]['filepath'];
            $filename = $cnt_result_th[0]['filename'];
            $pic_pid = $cnt_result_th[0]['pid'];
            $thumb_img = str_replace('.mp4', '.jpg', $filename);
            if (str_ends_with($filename, '.mp4')) {
                $thumb_img = "thumb_".$thumb_img;
            }

            $cat_val = [
                "id" => intval($param_cat_id),
                "name" => $cat_name,
                "comment" => "",
                "permalink" => null,
                "status" => "public",
                "uppercats" => $param_cat_id,
                "global_rank" => $pos_num,
                "id_uppercat" => null,
                "nb_images" => 0,
                "total_nb_images" => intval($pic_count),
                "representative_picture_id" => $pic_pid,
                "date_last" => null,
                "max_date_last" => "2024-02-23 21:24:05",
                "nb_categories" => intval($alb_count),
                "image_order" => "date_available DESC, file ASC, id ASC",
                "url" => $_SESSION["api_hostname"]."index.php?cat=$cat_id",
                "tn_url" => $_SESSION["api_hostname"]."albums/".$path.$thumb_img
            ];
            array_push($cat_list,$cat_val);

            $albums = cpg_db_fetch_rowset(
                cpg_db_query("SELECT aid, title, pos FROM {$CONFIG['TABLE_ALBUMS']} WHERE category = $param_cat_id"));
            foreach ($albums as $roww) {
                $alb_id = $roww['aid'];
                $alb_name = $roww['title'];
                $pos_num = $roww['pos'];

                $cnt_result = cpg_db_fetch_rowset(
                cpg_db_query("SELECT count(pic.pid) as pic_count FROM {$CONFIG['TABLE_PICTURES']} as pic WHERE pic.aid in (select aid from {$CONFIG['TABLE_ALBUMS']} as alb where alb.aid = $alb_id)"));
                $pic_count = $cnt_result[0]['pic_count'];

                $cnt_result_th = cpg_db_fetch_rowset(
                cpg_db_query("SELECT pid, filepath, filename FROM {$CONFIG['TABLE_PICTURES']} as pic WHERE pic.aid in (select alb.aid from {$CONFIG['TABLE_ALBUMS']} as alb where alb.aid = $alb_id)"));

                $path = $cnt_result_th[0]['filepath'];
                $filename = $cnt_result_th[0]['filename'];
                $pic_pid = $cnt_result_th[0]['pid'];
                $thumb_img = str_replace('.mp4', '.jpg', $filename);

                $cat_val = [
                    "id" => intval($alb_id),
                    "name" => $alb_name,
                    "comment" => "",
                    "permalink" => null,
                    "status" => "public",
                    "uppercats" => $param_cat_id . "," . $alb_id,
                    "global_rank" => $pos_num,
                    "id_uppercat" => $param_cat_id,
                    "nb_images" => intval($pic_count),
                    "total_nb_images" => intval($pic_count),
                    "representative_picture_id" => $pic_pid,
                    "date_last" => null,
                    "max_date_last" => "2024-02-23 21:24:05",
                    "nb_categories" => 0,
                    "image_order" => "date_available DESC, file ASC, id ASC",
                    "url" => $_SESSION["api_hostname"]."thumbnails.php?album=$alb_id",
                    "tn_url" => $_SESSION["api_hostname"]."albums/".$path."thumb_".$thumb_img
                ];
                array_push($cat_list,$cat_val);
            }
            $pwg_categories_getList["result"]["categories"] = $cat_list;
            //echo $pwg_categories_getList2;
            echo json_encode($pwg_categories_getList);
        
        // 画像一覧用
        } else {
            $top_album = cpg_db_fetch_rowset(
                cpg_db_query("SELECT aid, title, category, pos FROM {$CONFIG['TABLE_ALBUMS']} WHERE aid = $param_cat_id"));
            $alb_id = $top_album[0]['aid'];
            $alb_name = $top_album[0]['title'];
            $cat_id = $top_album[0]['category'];
            $pos_num = $top_album[0]['pos'];

            $cnt_result = cpg_db_fetch_rowset(
            cpg_db_query("SELECT count(pic.pid) as pic_count FROM {$CONFIG['TABLE_PICTURES']} as pic WHERE pic.aid in (select aid from {$CONFIG['TABLE_ALBUMS']} as alb where alb.aid = $alb_id)"));
            $pic_count = $cnt_result[0]['pic_count'];

            $cnt_result_th = cpg_db_fetch_rowset(
            cpg_db_query("SELECT pid, filepath, filename FROM {$CONFIG['TABLE_PICTURES']} as pic WHERE pic.aid in (select aid from {$CONFIG['TABLE_ALBUMS']} as alb where alb.aid = $alb_id)"));

            $path = $cnt_result_th[0]['filepath'];
            $filename = $cnt_result_th[0]['filename'];
            $pic_pid = $cnt_result_th[0]['pid'];
            $thumb_img = str_replace('.mp4', '.jpg', $filename);

            $cat_val = [
                "id" => intval($param_cat_id),
                "name" => $alb_name,
                "comment" => "",
                "permalink" => null,
                "status" => "public",
                "uppercats" => $cat_id . "," . $param_cat_id,
                "global_rank" => $pos_num,
                "id_uppercat" => $cat_id,
                "nb_images" => intval($pic_count),
                "total_nb_images" => intval($pic_count),
                "representative_picture_id" => $pic_pid,
                "date_last" => null,
                "max_date_last" => "2024-02-23 21:24:05",
                "nb_categories" => 0,
                "image_order" => "date_available DESC, file ASC, id ASC",
                "url" => $_SESSION["api_hostname"]."thumbnails.php?album=$alb_id",
                "tn_url" => $_SESSION["api_hostname"]."albums/".$path."thumb_".$thumb_img
            ];
            array_push($cat_list,$cat_val);
            $pwg_categories_getList["result"]["categories"] = $cat_list;
            echo json_encode($pwg_categories_getList);
        }
    // カテゴリ用
    } else {
        $categories = cpg_db_fetch_rowset(
        cpg_db_query("SELECT cid, name, pos FROM {$CONFIG['TABLE_CATEGORIES']} WHERE cid != 1"));
        $outpt = "";

        foreach ($categories as $row) {
            $cat_id = $row['cid'];
            $cat_name = $row['name'];
            $pos_num = $row['pos'];
            

            $cnt_result_alb = cpg_db_fetch_rowset(
                cpg_db_query("select count(aid) as alb_count from {$CONFIG['TABLE_ALBUMS']} as alb where alb.category = $cat_id"));
            $alb_count = $cnt_result_alb[0]['alb_count'];

            $cnt_result = cpg_db_fetch_rowset(
                cpg_db_query("SELECT count(pic.pid) as pic_count FROM {$CONFIG['TABLE_PICTURES']} as pic WHERE pic.aid in (select aid from {$CONFIG['TABLE_ALBUMS']} as alb where alb.category = $cat_id)"));
            $pic_count = $cnt_result[0]['pic_count'];

            $cnt_result_th = cpg_db_fetch_rowset(
                cpg_db_query("SELECT pid, filepath, filename FROM {$CONFIG['TABLE_PICTURES']} as pic WHERE pic.aid in (select aid from {$CONFIG['TABLE_ALBUMS']} as alb where alb.category = $cat_id)"));
    
            $path = $cnt_result_th[0]['filepath'];
            $filename = $cnt_result_th[0]['filename'];
            $pic_pid = $cnt_result_th[0]['pid'];
            $thumb_img = str_replace('.mp4', '.jpg', $filename);
            if (str_ends_with($filename, '.mp4')) {
                $thumb_img = "thumb_".$thumb_img;
            }

            $cat_val = [
                "id" => intval($cat_id),
                "name" => $cat_name,
                "comment" => "",
                "permalink" => null,
                "status" => "public",
                "uppercats" => $cat_id,
                "global_rank" => $pos_num,
                "id_uppercat" => null,
                "nb_images" => 0,
                "total_nb_images" => intval($pic_count),
                "representative_picture_id" => $pic_pid,
                "date_last" => null,
                "max_date_last" => "2024-02-23 21:24:05",
                "nb_categories" => intval($alb_count),
                "image_order" => "date_available DESC, file ASC, id ASC",
                "url" => $_SESSION["api_hostname"]."index.php?cat=".$cat_id,
                "tn_url" => $_SESSION["api_hostname"]."albums/".$path.$thumb_img
            ];
            array_push($cat_list,$cat_val);

            // $albums = cpg_db_fetch_rowset(
            // cpg_db_query("SELECT aid, title FROM {$CONFIG['TABLE_ALBUMS']} WHERE category = $cat_id"));
            // foreach ($albums as $roww) {
            //     $alb_id = $roww['aid'];
            //     $alb_name = $roww['title'];
            //     $pictures = cpg_db_fetch_rowset(cpg_db_query(
            //     "SELECT filepath, filename FROM {$CONFIG['TABLE_PICTURES']} WHERE aid = $alb_id"));
            //     foreach ($pictures as $rowww) {
            //         $path = $rowww['filepath'];
            //         $filename = $rowww['filename'];
            //         $outpt = "$cat_name;;$alb_name;;$path$filename\n";
            //     }
            // }
        }

        $pwg_categories_getList["result"]["categories"] = $cat_list;
        echo json_encode($pwg_categories_getList);
    }

    //echo json_encode($pwg_categories_getList);
    //echo json_encode($pwg_categories_getList,JSON_NUMERIC_CHECK|JSON_UNESCAPED_UNICODE );
    //echo $pwg_categories_getList;
}

function pwg_categories_getImages() {
    global $CONFIG,$param_method,$param_cat_id,$param_image_id,$param_username,$param_password,$cookie_pwg_id;

    $pwg_categories_getImages = [
        "stat" => "ok",
        "result" => [
            "paging" => [
                "page" => 0,
                "per_page" => 100,
                "count" => 100,
                "total_count" => "999"
            ],
            "images" => []
        ]
    ];

    $img_list = [];

    $pictures = cpg_db_fetch_rowset(cpg_db_query(
    "SELECT pid, filepath, filename FROM {$CONFIG['TABLE_PICTURES']} WHERE aid = $param_cat_id"));
    foreach ($pictures as $rowww) {
        $pic_id = $rowww['pid'];
        $path = $rowww['filepath'];
        $filename = $rowww['filename'];
        $thumb_img = str_replace('.mp4', '.jpg', $filename);
        $img_val = [
            "is_favorite" => false,
            "id" => intval($pic_id),
            "width" => 400,
            "height" => 300,
            "hit" => 0,
            "file" => $filename,
            "name" => str_replace('.jpg', '', $thumb_img),
            "comment" => null,
            "date_creation" => null,
            "date_available" => "",
            "page_url" => $_SESSION["api_hostname"]."displayimage.php?album=".$param_cat_id."&pid=".$pic_id,
            "element_url" => $_SESSION["api_hostname"]."albums/".$path.$filename,
            "download_url" => $_SESSION["api_hostname"]."displayimage.php?album=".$param_cat_id."&pid=".$pic_id,
            "derivatives" => [
                "square" => [
                    "url" => $_SESSION["api_hostname"]."albums/".$path."thumb_".$thumb_img,
                    "width" => 120,
                    "height" => 120
                ],
                "thumb" => [
                    "url" => $_SESSION["api_hostname"]."albums/".$path."thumb_".$thumb_img,
                    "width" => 144,
                    "height" => 108
                ],
                "2small" => [
                    "url" => $_SESSION["api_hostname"]."albums/".$path."thumb_".$thumb_img,
                    "width" => 240,
                    "height" => 180
                ],
                "xsmall" => [
                    "url" => $_SESSION["api_hostname"]."albums/".$path."thumb_".$thumb_img,
                    "width" => "400",
                    "height" => "300"
                ],
                "small" => [
                    "url" => $_SESSION["api_hostname"]."albums/".$path."thumb_".$thumb_img,
                    "width" => "400",
                    "height" => "300"
                ],
                "medium" => [
                    "url" => $_SESSION["api_hostname"]."albums/".$path."thumb_".$thumb_img,
                    "width" => "400",
                    "height" => "300"
                ],
                "large" => [
                    "url" => $_SESSION["api_hostname"]."albums/".$path."thumb_".$thumb_img,
                    "width" => "400",
                    "height" => "300"
                ],
                "xlarge" => [
                    "url" => $_SESSION["api_hostname"]."albums/".$path."thumb_".$thumb_img,
                    "width" => "400",
                    "height" => "300"
                ],
                "xxlarge" => [
                    "url" => $_SESSION["api_hostname"]."albums/".$path."thumb_".$thumb_img,
                    "width" => "400",
                    "height" => "300"
                ]
            ],
            "categories" => [
                [
                    "id" => intval($param_cat_id),
                    "url" => $_SESSION["api_hostname"]."index.php?cat=$param_cat_id",
                    "page_url" => $_SESSION["api_hostname"]."thumbnails.php?album=$param_cat_id"
                ]
            ]
        ];

        array_push($img_list,$img_val);
    }

    $pwg_categories_getImages["result"]["images"] = $img_list;
    echo json_encode($pwg_categories_getImages);
}

function pwg_images_getInfo() {
    global $CONFIG,$param_method,$param_cat_id,$param_image_id,$param_username,$param_password,$cookie_pwg_id;

    $pwg_images_getInfo = [
        "stat" => "ok",
        "result" => []
    ];

    $top_picture = cpg_db_fetch_rowset(cpg_db_query(
        "SELECT pid, aid, filepath, filename FROM {$CONFIG['TABLE_PICTURES']} WHERE pid = $param_image_id"));
    $alb_id = $top_picture[0]['aid'];
    $path = $top_picture[0]['filepath'];
    $filename = $top_picture[0]['filename'];
    $thumb_img = str_replace('.mp4', '.jpg', $filename);
    $top_album = cpg_db_fetch_rowset(
        cpg_db_query("SELECT aid, title, category FROM {$CONFIG['TABLE_ALBUMS']} WHERE aid = $alb_id"));
    $alb_name = $top_album[0]['title'];
    $cat_id = $top_album[0]['category'];

    $pwg_images_getInfo["result"] = [
            "id" => intval($param_image_id),
            "file" => $filename,
            "date_available" => "",
            "date_creation" => null,
            "name" => str_replace('.jpg', '', $thumb_img),
            "comment" => null,
            "author" => null,
            "hit" => 0,
            "filesize" => 119,
            "width" => 400,
            "height" => 300,
            "coi" => null,
            "representative_ext" => null,
            "date_metadata_update" => "",
            "rating_score" => null,
            "level" => "0",
            "md5sum" => null,
            "added_by" => "1",
            "rotation" => null,
            "latitude" => null,
            "longitude" => null,
            "lastmodified" => "",
            "page_url" => $_SESSION["api_hostname"]."displayimage.php?album=".$alb_id."&pid=".$param_image_id,
            "element_url" => $_SESSION["api_hostname"]."albums/".$path.$filename,
            "download_url" => $_SESSION["api_hostname"]."albums/".$path.$filename,
            "derivatives" => [
                "square" => [
                    "url" => $_SESSION["api_hostname"]."albums/".$path."thumb_".$thumb_img,
                    "width" => 120,
                    "height" => 120
                ],
                "thumb" => [
                    "url" => $_SESSION["api_hostname"]."albums/".$path."thumb_".$thumb_img,
                    "width" => 144,
                    "height" => 108
                ],
                "2small" => [
                    "url" => $_SESSION["api_hostname"]."albums/".$path."thumb_".$thumb_img,
                    "width" => 240,
                    "height" => 180
                ],
                "xsmall" => [
                    "url" => $_SESSION["api_hostname"]."albums/".$path."thumb_".$thumb_img,
                    "width" => "400",
                    "height" => "300"
                ],
                "small" => [
                    "url" => $_SESSION["api_hostname"]."albums/".$path."thumb_".$thumb_img,
                    "width" => "400",
                    "height" => "300"
                ],
                "medium" => [
                    "url" => $_SESSION["api_hostname"]."albums/".$path."thumb_".$thumb_img,
                    "width" => "400",
                    "height" => "300"
                ],
                "large" => [
                    "url" => $_SESSION["api_hostname"]."albums/".$path."thumb_".$thumb_img,
                    "width" => "400",
                    "height" => "300"
                ],
                "xlarge" => [
                    "url" => $_SESSION["api_hostname"]."albums/".$path."thumb_".$thumb_img,
                    "width" => "400",
                    "height" => "300"
                ],
                "xxlarge" => [
                    "url" => $_SESSION["api_hostname"]."albums/".$path."thumb_".$thumb_img,
                    "width" => "400",
                    "height" => "300"
                ]
            ],
            "rates" => [
                "score" => null,
                "count" => 0,
                "average" => null
            ],
            "categories" => [
                [
                    "id" => intval($alb_id),
                    "name" => $alb_name,
                    "permalink" => null,
                    "uppercats" => $cat_id . "," . $alb_id,
                    "global_rank" => "1.1",
                    "url" => $_SESSION["api_hostname"]."index.php?/category/2",
                    "page_url" => $_SESSION["api_hostname"]."thumbnails.php?album=$param_cat_id"
                ]
            ],
            "tags" => [],
            "comments_paging" => [
                "page" => 0,
                "per_page" => "10",
                "count" => 0,
                "total_count" => 0
            ],
            "comments" => []
    ];

    echo json_encode($pwg_images_getInfo);
}

//header("Access-Control-Allow-Origin: *");
header('Content-Type: application/json');

if($param_method == 'pwg.session.login') {
    pwg_session_login();
} else if($param_method == 'pwg.session.logout') {
    pwg_session_logout();
} else if($param_method == 'pwg.session.getStatus') {
    pwg_session_getStatus();
} else if($param_method == 'reflection.getMethodList') {
    pwg_reflection_getMethodList();
} else if($param_method == 'pwg.categories.getList') {
    pwg_categories_getList();
} else if($param_method == 'pwg.categories.getImages') {
    pwg_categories_getImages();
} else if($param_method == 'pwg.images.getInfo') {
    pwg_images_getInfo();
}


//$pwg_session_login = '{"stat":"ok","result":true}';
//$pwg_session_getStatus = '{"stat":"ok","result":{"username":"admin","status":"webmaster","theme":"modus","language":"ja_JP","pwg_token":"10b64192eeced52216df8d5df4a1c563","charset":"utf-8","current_datetime":"2024-02-27 18:28:32","version":"14.2.0","save_visits":false,"available_sizes":["square","thumb","2small","xsmall","small","medium","large","xlarge","xxlarge"],"upload_file_types":"jpg,jpeg,png,gif,webp","upload_form_chunk_size":500}}';
//$reflection_getMethodList = '{"stat":"ok","result":{"methods":["pwg.activity.downloadLog","pwg.activity.getList","pwg.caddie.add","pwg.categories.add","pwg.categories.calculateOrphans","pwg.categories.delete","pwg.categories.deleteRepresentative","pwg.categories.getAdminList","pwg.categories.getImages","pwg.categories.getList","pwg.categories.move","pwg.categories.refreshRepresentative","pwg.categories.setInfo","pwg.categories.setRank","pwg.categories.setRepresentative","pwg.extensions.checkUpdates","pwg.extensions.ignoreUpdate","pwg.extensions.update","pwg.getCacheSize","pwg.getInfos","pwg.getMissingDerivatives","pwg.getVersion","pwg.groups.add","pwg.groups.addUser","pwg.groups.delete","pwg.groups.deleteUser","pwg.groups.duplicate","pwg.groups.getList","pwg.groups.merge","pwg.groups.setInfo","pwg.history.log","pwg.history.search","pwg.images.add","pwg.images.addChunk","pwg.images.addComment","pwg.images.addFile","pwg.images.addSimple","pwg.images.checkFiles","pwg.images.checkUpload","pwg.images.delete","pwg.images.deleteOrphans","pwg.images.emptyLounge","pwg.images.exist","pwg.images.filteredSearch.create","pwg.images.formats.delete","pwg.images.formats.searchImage","pwg.images.getInfo","pwg.images.rate","pwg.images.search","pwg.images.setCategory","pwg.images.setInfo","pwg.images.setMd5sum","pwg.images.setPrivacyLevel","pwg.images.setRank","pwg.images.syncMetadata","pwg.images.upload","pwg.images.uploadAsync","pwg.images.uploadCompleted","pwg.permissions.add","pwg.permissions.getList","pwg.permissions.remove","pwg.plugins.getList","pwg.plugins.performAction","pwg.rates.delete","pwg.session.getStatus","pwg.session.login","pwg.session.logout","pwg.tags.add","pwg.tags.delete","pwg.tags.duplicate","pwg.tags.getAdminList","pwg.tags.getImages","pwg.tags.getList","pwg.tags.merge","pwg.tags.rename","pwg.themes.performAction","pwg.users.add","pwg.users.delete","pwg.users.favorites.add","pwg.users.favorites.getList","pwg.users.favorites.remove","pwg.users.getAuthKey","pwg.users.getList","pwg.users.preferences.set","pwg.users.setInfo","reflection.getMethodDetails","reflection.getMethodList"]}}';
