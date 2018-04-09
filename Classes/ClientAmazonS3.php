<?php

use Aws\S3\S3Client;

class AmazonS3Client
{
    protected $s3;
    protected $accessKey;
    protected $secretKey;
    protected $bucket;
    protected $region;


    public function setAccessKey($accessKey)
    {
        $this->accessKey = $accessKey;
    }

    public function setSecretKey($secretKey)
    {
        $this->secretKey = $secretKey;
    }

    public function setBucket($bucket)
    {
        $this->bucket = $bucket;
    }

    public function setRegion($region)
    {
        $this->region = $region;
    }

    public function __construct($accessKey = null, $secretKey = null, $bucket = null, $region = null)
    {
        $this->accessKey = $accessKey;
        $this->secretKey = $secretKey;
        $this->bucket = $bucket;
        $this->region = $region;
    }

    protected function createService()
    {
        $s3 = new S3Client(
            array(
                'version' => 'latest',
                'region' => $this->region,
                'credentials' => array(
                    'key' => $this->accessKey,
                    'secret' => $this->secretKey
                )
            )
        );


        //$s3 = $aws->get($aws);

        return $s3;
    }

    public function getBuckets($createBucket = false)
    {
        $s3 = $this->createService();
        $result = $s3->listBuckets();
        $buckets = array();
        foreach ($result['Buckets'] as $key => $value) {
            $buckets[] = $value['Name'];
        }

        return $buckets;
    }

    /*
      Bucket name should conform with DNS requirements:
    - Should not contain uppercase characters
    - Should not contain underscores (_)
    - Should be between 3 and 63 characters long
    - Should not end with a dash
    - Cannot contain two, adjacent periods
    - Cannot contain dashes next to periods (e.g., "my-.bucket.com" and "my.-bucket" are invalid)
     */
    public function createBucket($bucket)
    {
        try {

            $param = array(
                'Bucket' => $bucket,
                'CreateBucketConfiguration' => array(
                    'LocationConstraint' => $this->region,
                )
            );
            $s3 = $this->createService();
            DUP_Logs::log("AmazonS3: creating Bucket.");
            $result = $s3->createBucket($param);
            return $result;
        } catch(\Aws\S3\Exception\S3Exception $ex)
        {
            throw new AmazonS3ClientException("Cannot create the bucket. Invalid name.");
        }
    }

    /*
     * With a single PutObject operation, you can upload objects up to 5 GB in size.
     * However, by using the multipart uploads, you can upload object up to 5 TB in size.
    */
    public function upload($file, $name)
    {
        if (!file_exists($file))
            throw new AmazonS3ClientException("AmazonS3: cannot upload <{$file}>, the file does not exist.");


        $param = array(
            'Bucket' => $this->bucket,
            'Key' => $name,
            'Body' => @fopen($file, 'rb'),
            'ACL' => 'public-read'
        );


        $s3 = $this->createService();
        DUP_Logs::log("AmazonS3: uploading file {$file}");
        if(DUP_Util::filesize($file) < 100) {
            $result = $s3->putObject($param);
            $result->get('ObjectURL');
        }
        else {
            DUP_Logs::log("AmazonS3: file is superior to 100MB, using MultiPartUploader.");
            $uploader = new \Aws\S3\MultipartUploader($s3, $file, array(
                'Bucket' => $this->bucket,
                'Key' => $name,
            ));
            $result = $uploader->upload();
        }
        DUP_Logs::log("AmazonS3: upload complete to {$result['ObjectURL']} on bucket {$this->bucket}.", 'message');
    }

    public function getFiles()
    {
        try {
            $params = array(
                'Bucket' => $this->bucket
            );
            $s3 = $this->createService();
            DUP_Logs::log("AmazonS3: getting files.");
            $result = $s3->listObjects($params);

            return $result->get('Contents');
        } catch(\Aws\S3\Exception\S3Exception $ex) {
            throw new AmazonS3ClientException("Cannot get files.");
        }
    }

    public function download($filename, $path)
    {
        try {
            $r = fopen($path, 'w+');
            $params = array(
                'Bucket' => $this->bucket,
                'Key' => $filename
                //'SaveAs' => $filename
            );
            $s3 = $this->createService();
            DUP_Logs::log("AmazonS3: downloading a file.");
            $result = $s3->getObject($params);
            file_put_contents ($path, (string) $result['Body']);
            return $result;
        } catch(\Aws\S3\Exception\S3Exception $ex) {
            throw new AmazonS3ClientException("Cannot get files.");
        }
    }

    public function deleteFile(array $files)
    {
        $param = array(
            'Bucket' => $this->bucket
        );


        $s3 = $this->createService();
        DUP_Logs::log("AmazonS3: deleting files...");
        $result = null;
        foreach ($files as $file) {
            $param['Key'] = $file['Key'];
            $result = $s3->deleteObject($param);
            DUP_Logs::log("AmazonS3: deleted {$file['Key']}");
        }

        return $result;
    }


    //public function deleteOldBackups($retaindays, $retaincount, $deadline = null)
    public function deleteOldBackups($retaincount, $deadline = null)
    {
        try {
            if ($retaincount < 1 && empty($deadline)) return; // cleanup disabled.

            $s3 = $this->createService();

            $toDelete = array();
            $n = 0;
            /*$objects = $s3->getIterator('ListObjects', array(
                'Bucket' => $this->bucket
            ))->next();*/

            $objects = $s3->getIterator('ListObjects', array(
                'Bucket' => $this->bucket
            ));

            foreach ($objects as $object) {
                $keys[] = $object['Key'];
            }

            array_multisort($keys, SORT_DESC);

            foreach ($keys as $key) {
                $n++;
                //$key   = $object['Key'];
                $tsstr = basename($key, '.package.zip');
                $parts = explode('-', $tsstr);
                array_pop($parts);
                $tsstr = implode('-', $parts);
                $ts = date_create_from_format(Duplicator::DUP_TIMESTAMP_FORMAT, $tsstr);
                if ($ts == false) continue;
                $shouldDelete = ($retaincount > 0 && $n > $retaincount) || $ts->getTimestamp() < (strtotime("-{$deadline}"));
                if ($shouldDelete) {
                    $toDelete[] = array('Key' => $key);
                }
            }

            if (count($toDelete)) {
                $this->deleteFile($toDelete);
            }
        } catch(\Exception $ex) {
            throw new AmazonS3ClientException("An error occured while deleting old backups.");
        }
    }


}


class AmazonS3ClientException extends \Exception
{
    public function __construct($message = null, $code = 0, Exception $previous = null) {
        DUP_Logs::log($message);
        parent::__construct($message, $code, $previous);
    }
}