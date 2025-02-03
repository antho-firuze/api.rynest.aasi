<?php
defined('BASEPATH') OR exit('No direct script access allowed');
 
class FTPConnection
{
    private $connection;
    private $ftp;

    // public function __construct()
    public function __construct($host, $port=21, $user, $pass)
    {
        $this->connection = @ftp_connect($host, $port);
        if (! $this->connection)
            throw new Exception("Could not connect to $host on port $port.");

        if (! @ftp_login($this->connection, $user, $pass))
        throw new Exception("Could not authenticate with username $user " .
                            "and password $pass.");
    }

    // public function login($username, $password)
    // {
    //     if (! $login_result = @ftp_login($this->connection, $username, $password))
    //         throw new Exception("Could not authenticate with username $username " .
    //                             "and password $password.");

    //     if ((!$this->connection) || (!$login_result))
    //         throw new Exception("Could not initialize FTP subsystem.");
        
    //     return true;
    // }

    public function uploadFile($local_file, $remote_file)
    {
        // Using passive mode is a must
        ftp_pasv($this->connection, true);

        $upload = ftp_put($this->connection, $remote_file, $local_file, FTP_BINARY);  // upload the file
        // $upload = ftp_put($this->connection, $remote_file, $local_file, FTP_BINARY);  // upload the file
        if (!$upload)
            throw new Exception("<h2>FTP upload of $myFileName has failed!</h2> <br />");
        
        ftp_close($this->connection); // close the FTP stream
        return true;
    }

    public function mk_dir($ftpbasedir, $dir)
    {
        try {
            @ftp_chdir($this->connection, $ftpbasedir);
            // try to create the directory $dir
            if (! @ftp_chdir($this->connection, $dir)){
                if (! ftp_mkdir($this->connection, $dir)) 
                    throw new Exception("<h2>There was a problem while creating $dir</h2> <br />");

                ftp_chmod($this->connection, 0775, $dir);
            }

            // ftp_close($this->connection); // close the FTP stream
            return true;
        } 
        catch (Exception $e)
        {
            throw new Exception($e);
        }
    }

}