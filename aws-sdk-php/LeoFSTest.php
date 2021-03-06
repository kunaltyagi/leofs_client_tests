<?php
// Include the SDK using the Composer autoloader
require "vendor/autoload.php";
use Aws\Common\Enum\Region;
use Aws\S3\S3Client;
use Aws\S3\Model\MultipartUpload\UploadBuilder;
use Aws\Common\Exception\MultipartUploadException;
use Aws\S3\Exception\NoSuchKeyException;
use Guzzle\Http\EntityBody;
use Guzzle\Http\ReadLimitEntityBody;

define('HOST',  "localhost");
define('PORT',  8080);

define('ACCESS_KEY_ID', "05236");
define('SECRET_ACCESS_KEY', "802562235");
define('SIGN_VER', "v4");
define('BUCKET', "testp");
define('TEMP_DATA', "../temp_data/");

define('SMALL_TEST_F', TEMP_DATA."testFile");
define('MEDIUM_TEST_F', TEMP_DATA."testFile.medium");
define('LARGE_TEST_F', TEMP_DATA."testFile.large");

define('METADATA_KEY', "cmeta_key");
define('METADATA_VAL', "cmeta_val");

$s3;    // S3 Client

$signVer = SIGN_VER;
$host = HOST;
$port = PORT;
$bucket = BUCKET;
if ($argc > 1) {
   $signVer = $argv[1];
   $host = $argv[2];
   $port = intval($argv[3]);
   $bucket = $argv[4];
}

try {
    $metadataMap = [METADATA_KEY => METADATA_VAL];

    init($signVer);
    createBucket($bucket);

    // Put Object Test
    putObject($bucket, "test.simple",    SMALL_TEST_F);
    putObject($bucket, "test.medium",    MEDIUM_TEST_F);
    putObject($bucket, "test.large",     LARGE_TEST_F);

    // Multipart Upload Test
    mpObject($bucket, "test.simple.mp",  SMALL_TEST_F);
    mpObject($bucket, "test.large.mp",   LARGE_TEST_F);

	// Put Object with Metadata Test
    putObjectWithMetadata($bucket, "test.simple.meta", SMALL_TEST_F, $metadataMap);
	putObjectWithMetadata($bucket, "test.large.meta", LARGE_TEST_F, $metadataMap);

    // Object Metadata Test
    headObject($bucket, "test.simple",   SMALL_TEST_F);
    headObject($bucket, "test.large",    LARGE_TEST_F);
// MP File ETag != MD5
//    headObject($bucket, "test.simple.mp", SMALL_TEST_F);
//    headObject($bucket, "test.large.mp", LARGE_TEST_F);

    // Get Object Test
    getObject($bucket, "test.simple",    SMALL_TEST_F);
    getObject($bucket, "test.simple.mp", SMALL_TEST_F);
    getObject($bucket, "test.medium",    MEDIUM_TEST_F);
    getObject($bucket, "test.large",     LARGE_TEST_F);
    getObject($bucket, "test.large.mp",  LARGE_TEST_F);

    // Get Object Again (Cache) Test
    getObject($bucket, "test.simple",    SMALL_TEST_F);
    getObject($bucket, "test.simple.mp", SMALL_TEST_F);
    getObject($bucket, "test.medium",    MEDIUM_TEST_F);
    getObject($bucket, "test.large",     LARGE_TEST_F);

    // Get Object with Metadata Test
    getObjectWithMetadata($bucket, "test.simple.meta", SMALL_TEST_F, $metadataMap);
	getObjectWithMetadata($bucket, "test.large.meta", LARGE_TEST_F, $metadataMap);

    // Get Not Exist Object Test
    getNotExist($bucket, "test.noexist");

    // Range Get Object Test
    rangeObject($bucket, "test.simple",      SMALL_TEST_F, 1, 4); 
    rangeObject($bucket, "test.simple.mp",   SMALL_TEST_F, 1, 4); 
    rangeObject($bucket, "test.large",       LARGE_TEST_F, 1048576, 10485760); 
    rangeObject($bucket, "test.large.mp",    LARGE_TEST_F, 1048576, 10485760); 

    // Copy Object Test
    copyObject($bucket, "test.simple", "test.simple.copy");
    getObject($bucket, "test.simple.copy", SMALL_TEST_F);

    // List Object Test
    listObject($bucket, "", -1);

    // Delete All Object Test
    deleteAllObjects($bucket);
    sleep(3);
    listObject($bucket, "", 0);

    // Multiple Page List Object Test
    putDummyObjects($bucket, "list/", 35, SMALL_TEST_F);
    sleep(3);
    pageListBucket($bucket, "list/", 35, 10);

    // Multiple Delete
    multiDelete($bucket, "list/", 10);

    // GET-PUT ACL
    setBucketAcl($bucket, "private");
    setBucketAcl($bucket, "public-read");
    setBucketAcl($bucket, "public-read-write");
} catch (\Aws\S3\Exception\S3Exception $e){
    // Exception messages
    print $e->getMessage();
	exit(1);
} catch (Exception $e) {
    print $e->getMessage();
	exit(1);
}

function init($signVer) {
    global $s3;
	global $host;
	global $port;

    $options = array(
        "key"       => ACCESS_KEY_ID,
        "secret"    => SECRET_ACCESS_KEY,
        "region"    => Region::US_WEST_2,
        "scheme"    => "http",
        "base_url"  => "http://".$host.":".$port
    );
    if ($signVer == "v4") {
        $options["signature"] = $signVer;
    }
    $s3 = S3Client::factory($options);
}

function createBucket($bucketName) {
    global $s3;
    printf("===== Create Bucket [%s] Start =====\n", $bucketName);
    $s3->createBucket(array(
        "Bucket"    => $bucketName
    )); 
    print "===== Create Bucket End =====\n";
    print "\n";
}

function putObject($bucketName, $key, $path) {
    global $s3;
    printf("===== Put Object [%s/%s] Start =====\n", $bucketName, $key);
    $s3->putObject(array(
        "Bucket"    => $bucketName,
        "Key"       => $key,
        "Body"      => fopen($path, "r")
    ));
    if (!$s3->doesObjectExist($bucketName, $key)) {
        throw new Exception(sprintf("Put Object [%s/%s] Failed!\n", $bucketName, $key));
    }
    print "===== Put Object End =====\n";
    print "\n";
}

function putObjectWithMetadata($bucketName, $key, $path, $meta_map) {
    global $s3;
    printf("===== Put Object [%s/%s] with Metadata Start =====\n", $bucketName, $key);
    $s3->putObject(array(
        "Bucket"    => $bucketName,
        "Key"       => $key,
        "Body"      => fopen($path, "r"),
        "Metadata"  => $meta_map
    ));
    if (!$s3->doesObjectExist($bucketName, $key)) {
        throw new Exception(sprintf("Put Object [%s/%s] with Metadata Failed!\n", $bucketName, $key));
    }
    print "===== Put Object with Metadata End =====\n";
    print "\n";
}

function mpObject($bucketName, $key, $path) {
    global $s3;
    printf("===== Multipart Upload Object [%s/%s] Start =====\n", $bucketName, $key);
    $uploader = UploadBuilder::newInstance()
        ->setClient($s3)
        ->setBucket($bucketName)
        ->setKey($key)
        ->setSource($path)
        ->build();
    $uploader->upload();
    if (!$s3->doesObjectExist($bucketName, $key)) {
        throw new Exception(sprintf("Multipart Uplaod Object [%s/%s] Failed!\n", $bucketName, $key));
    }
    print "===== Multipart Upload Object End =====\n";
    print "\n";
}

function headObject($bucketName, $key, $path) {
    global $s3;
    printf("===== Head Object [%s/%s] Start =====\n", $bucketName, $key);
    $res = $s3->headObject(array(
        "Bucket"    => $bucketName,
        "Key"       => $key
    ));
    printf("ETag: %s, Size: %d\n", trim($res["ETag"], "\""), $res["ContentLength"]);
    if ($res["ContentLength"] != filesize($path) || trim($res["ETag"],"\"") != md5_file($path)) {
        throw new Exception(sprintf("Metadata [%s/%s] NOT Match, Size: %d, MD5: %s\n", $bucketName, $key, filesize($path), md5_file($path)));
    }
    print "===== Head Object End =====\n";
    print "\n";

}

function getObject($bucketName, $key, $path) {
    global $s3;
    printf("===== Get Object [%s/%s] Start =====\n", $bucketName, $key);
    $res = $s3->getObject(array(
        "Bucket"    => $bucketName,
        "Key"       => $key
    ));
    $content = $res["Body"];
    $content->rewind();
    $file = EntityBody::factory(fopen($path, "r"));
    if (!doesFileMatch($content, $file)) {
        throw new Exception("Content NOT Match!\n");
    }
    print "===== Get Object End =====\n";
    print "\n";
    return $res;
}

function getObjectWithMetadata($bucketName, $key, $path, $meta_map) {
    global $s3;
    printf("===== Get Object [%s/%s] with Metadata Start =====\n", $bucketName, $key);
    $res = $s3->getObject(array(
        "Bucket"    => $bucketName,
        "Key"       => $key
    ));
    $content = $res["Body"];
    $meta = $res["Metadata"];
    if ($meta != $meta_map) {
        throw new Exception("Metadata NOT Match!\n");
    }
    $content->rewind();
    $file = EntityBody::factory(fopen($path, "r"));
    if (!doesFileMatch($content, $file)) {
        throw new Exception("Content NOT Match!\n");
    }
    print "===== Get Object with Metadata End =====\n";
    print "\n";
    return $res;
}

function getNotExist($bucketName, $key) {
    global $s3;
    printf("===== Get Not Exist Object [%s/%s] Start =====\n", $bucketName, $key);
    try {
        $res = $s3->getObject(array(
            "Bucket"    => $bucketName,
            "Key"       => $key
        ));
        throw new Exception("Should NOT Exist!\n");
    } catch (\Aws\S3\Exception\NoSuchKeyException $e) {
        ;
    }
    print "===== Get Not Exist Object End =====\n";
    print "\n";
}

function rangeObject($bucketName, $key, $path, $start, $end) {
    global $s3;
    printf("===== Range Get Object [%s/%s] (%d-%d) Start =====\n", $bucketName, $key, $start, $end);
    $res = $s3->getObject(array(
        "Bucket"    => $bucketName,
        "Key"       => $key,
        "Range"     => "bytes=".$start."-".$end
    ));
    $content = $res["Body"];
    $content->rewind();
    $fileBody = EntityBody::factory(fopen($path, "r"));
    $file = new ReadLimitEntityBody($fileBody, $end - $start + 1, $start);

    if (!doesFileMatch($content, $file)) {
        throw new Exception("Content NOT Match!\n");
    }
    print "===== Range Get Object End =====\n";
    print "\n";
}

function copyObject($bucketName, $src, $dst) {
    global $s3;
    printf("===== Copy Object [%s/%s] -> [%s/%s] Start =====\n",
        $bucketName, $src, $bucketName, $dst);
    $s3->copyObject(array(
        "Bucket"    => $bucketName,
        "CopySource"=> sprintf("/%s/%s", $bucketName, $src),
        "Key"       => $dst
    ));
    print "===== Copy Object End =====\n";
    print "\n";
}

function listObject($bucketName, $prefix, $expected) {
    global $s3;
    printf("===== List Objects [%s/%s*] Start =====\n", $bucketName, $prefix);
    $res = $s3->listObjects(array(
        "Bucket"    => $bucketName,
        "Prefix"    => $prefix
    ));
    $count = 0;
    foreach ($res["Contents"] as $obj) {
        if ($s3->doesObjectExist($bucketName, $obj["Key"])) {
            $count = $count + 1;
            printf("%s \t Size: %d\n", $obj["Key"], $obj["Size"]);
        }
    }
    if ($expected >= 0) {
        if ($count != $expected) {
            throw new Exception("Number of Objects NOT Match!\n");
        }
    }
    print "===== List Objects End =====\n";
    print "\n";
}

function deleteAllObjects($bucketName) {
    global $s3;
    printf("===== Delete All Objects [%s] Start =====\n", $bucketName);
    $res = $s3->listObjects(array(
        "Bucket"    => $bucketName
    ));
    foreach ($res["Contents"] as $obj) {
        $s3->deleteObject(array(
            "Bucket"    => $bucketName,
            "Key"       => $obj["Key"]
        ));
    }
    print "===== Delete All Objects End =====\n";
    print "\n";
}

function putDummyObjects($bucketName, $prefix, $total, $holder) {
    global $s3;
    for ($i = 0; $i < $total; $i++) {
        $s3->putObject(array(
            "Bucket"    => $bucketName,
            "Key"       => $prefix.$i,
            "Body"      => fopen($holder, "r")
        ));
    }
}

function pageListBucket($bucketName, $prefix, $total, $pageSize) {
    global $s3;
    printf("===== Multiple Page List Objects [%s/%s*] Start =====\n", $bucketName, $prefix);
    $marker = "";
    $count = 0;
    while(true){
        $res = $s3->listObjects(array(
            "Bucket"    => $bucketName,
            "Prefix"    => $prefix,
            "MaxKeys"   => $pageSize,
            "Marker"    => $marker
        ));
        print "===== Page =====\n";
        foreach ($res["Contents"] as $obj) {
            if ($s3->doesObjectExist($bucketName, $obj["Key"])) {
                $count = $count + 1;
                printf("%s \t Size: %d \t Count: %d\n", $obj["Key"], $obj["Size"], $count);
            }
        }
        if (!$res["IsTruncated"]) {
            break;
        } else {
            $marker = $res["NextMarker"];
        }
    }
    print "===== End =====\n";
    if ($count != $total) {
        throw new Exception("Number of Objects NOT Match!\n");
    }
    print "===== Multiple Page List Objects End =====\n";
    print "\n";

}

function multiDelete($bucketName, $prefix, $total) {
    global $s3;
    printf("===== Multiple Delete Objects [%s/%s] Start =====\n", $bucketName, $prefix);
    $delKeyList = array();
    for ($i = 0; $i < $total; $i++) {
        $delKeyList[] = array("Key" => $prefix.$i);
    }
    $res = $s3->deleteObjects(array(
        "Bucket"    => $bucketName,
        "Objects"   => $delKeyList
    ));
    foreach ($res["Deleted"] as $obj) {
        printf("Deleted %s/%s\n", $bucketName, $obj["Key"]);
    } 
    if (sizeof($res["Deleted"]) != $total) {
        throw new Exception("Number of Objects NOT Match!\n");
    }
    print "===== Multiple Delete Objects End =====\n";
    print "\n";
}

function setBucketAcl($bucketName, $permission) {
    global $s3;
    printf("===== Set Bucket ACL [%s] (%s) Start =====\n", $bucketName, $permission);
    $checkList = array();
    if ($permission == "private") {
        array_push($checkList, "FULL_CONTROL");
    } else if($permission == "public-read") {
        array_push($checkList, "READ");
        array_push($checkList, "READ_ACP");
    } else if($permission == "public-read-write") {
        array_push($checkList, "READ");
        array_push($checkList, "READ_ACP");
        array_push($checkList, "WRITE");
        array_push($checkList, "WRITE_ACP");
    } else {
        throw new Exception("Invalid Permission!\n");
    }

    $s3->putBucketAcl(array(
        "Bucket"    => $bucketName,
        "ACL"       => $permission
    ));
    $res = $s3->getBucketAcl(array(
        "Bucket"    => $bucketName
    ));
    printf("Owner ID: S3Owner [name=%s,id=%s]\n", $res["Owner"]["DisplayName"], $res["Owner"]["ID"]);
    $list = array();
    foreach ($res["Grants"] as $grant) {
        printf("Grantee: %s \t Permissions: %s\n", $grant["Grantee"]["URI"], $grant["Permission"]);
        array_push($list, $grant["Permission"]);
    }

    foreach ($checkList as $item) {
        if (!in_array($item, $list)) {
            throw new Exception("ACL NOT Match!\n");
        }
    }

    print "===== Set Bucket ACL End =====\n";
    print "\n";
}

function doesFileMatch($stream1, $stream2) {
    $count = 0;
    if ($stream1->getSize() != $stream1->getSize()) {
        return false;
    }
    while($count < $stream1->getSize()) {
        $b1 = $stream1->read(4096);
        $b2 = $stream2->read(4096);
        $count = $count + strlen($b1);
        if ($b1 != $b2) {
            return false;
        }
    }
    return true;
}

?>
