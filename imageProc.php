<?php

/**
 * Description of imageProc
 *
 * @author mikepearce
 * @todo Move completed files to different folder and zip up
 * @todo Output text results to screen
 * @todo Support RAW files (how?)
 * @todo Rename of files (sequentially, datetime, other?)
 */
class imageProc {

    private $_inputDir;
    private $_dateStyle;
    private $_deleteImages;
    private $_packageImages;
    private $_outputDir;
    private $_imageTypes;
    private $_dirYear;
    private $_dirMonth;
    private $_dirDay;
    private $_logfile;
    private $_errorLog;
    private $_count;

    public function __construct()
    {
        $this->_workingDir      = NULL;
        $this->_dateStyle       = NULL;
        $this->_deleteImages    = NULL;
        $this->_outputDir       = NULL;
        $this->_dirDay          = NULL;
        $this->_dirMonth        = NULL;
        $this->_dirYear         = NULL;
        $this->_logfile         = 'log.txt';
        $this->_errorLog        = 'errorlog.txt';
        $this->_count           = 0;

        $this->setImageTypes(array('.jpg', '.jpeg', '.tif', '.tiff'));
    }

    public function setPackageImages($b = TRUE)
    {
        $this->_packageImages = $b;
        return $this;
    }

    public function getPackageImages()
    {
        return $this->_packageImages;
    }

    public function setDirYear($d = TRUE)
    {
        $this->_dirYear = $d;
        return $this;
    }

    public function setDirMonth($d = TRUE)
    {
        $this->_dirMonth = $d;
        return $this;
    }

    public function setDirDay($d = TRUE)
    {
        $this->_dirDay = $d;
        return $this;
    }

    public function setImageTypes($types)
    {
        $this->_imageTypes = $types;
        return $this;
    }

    private function _getRegexImageTypes()
    {
        $images = '';
        foreach($this->_imageTypes AS $imageTypes)
        {
            $images .= $imageTypes ."|";
        }
        return '/('. substr($images, 0, -1) .')$/i';
    }

    public function setInputDir($dir)
    {
        $this->_inputDir = $dir;
        return $this;
    }

    private function _getInputDir()
    {
        return $this->_inputDir;
    }

    public function setDateStyle($dateStyle = 'Ymd')
    {
        $this->_dateStyle = $dateStyle;
        return $this;
    }

    private function _getDateStyle()
    {
        return $this->_dateStyle;
    }

    public function setDeleteImages($del = FALSE)
    {
        $this->_deleteImages = $del;
        return $this;
    }

    private function _getDeleteImages()
    {
        return $this->_deleteImages;
    }

    public function setOutputDir($dir)
    {
        $this->_outputDir = $dir;
        return $this;
    }
    
    private function _getOutputDir()
    {
        return $this->_outputDir;
    }

    public function go()
    {
        // First, make the ouput main dir
        if (!file_exists($this->_getOutputDir())) {
            if (!mkdir($this->_getOutputDir(), 0777, TRUE))
            {
                throw new Exception('Unable to create directory '. $this->_getOutputDir());
            }
        }        

        // Open the log file
        $log = fopen($this->_getOutputDir() ."/". $this->_logfile, "w+");
        $error_log = @fopen($this->_getOutputDir() ."/". $this->_errorLog, "w+");

        // Get the iterator object
        $iterator = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($this->_getInputDir()),
                RecursiveIteratorIterator::SELF_FIRST
        );

        foreach ($iterator as $file) {

            if (
                $file->isFile() AND
                preg_match($this->_getRegexImageTypes(), $file->getFilename()))
            {

                $exif = exif_read_data($file->getPathname());
                
                if (preg_match('/(EXIF|IFD0)/', $exif['SectionsFound']))
                {
                    if (isset($exif['DateTimeOriginal']))
                    {
                        $date = $exif['DateTimeOriginal'];
                    }
                    else {
                        $date = date('Y/m/d H:i:s', $exif['FileDateTime']);
                    }

                    $newPath =  $this->_getOutputDir() ."/";
                    $unixTime = strtotime($date);
                    
                    if ($this->_dirYear)
                    {
                        $newPath .= date('Y', $unixTime) ."/";
                    }
                    
                    if ($this->_dirMonth)
                    {
                        $newPath .= date('m', $unixTime) ."/";
                    }

                    if ($this->_dirDay)
                    {
                        $newPath .= date('d', $unixTime) ."/";
                    }

                    if (!file_exists($newPath)) {
                        if (!mkdir($newPath, 0777, TRUE))
                        {
                            throw new Exception('Unable to create directory '. $newPath);
                        }
                    }
                    
                    // Get the filename
                    $fileName = $file->getFileName();
                    $i = 1;

                    // If it exists, keep trying until we've got a uniqye name
                    while (file_exists($newPath."/".$fileName))
                    {
                        $fileName = $i++ ."_". $fileName;
                    }

                    // Now we have a unique name, copy it.
                    copy($file->getPathName(), $newPath."/".$fileName);
                    fwrite($log, "File ". $fileName ." written.\n");

                    // Do we want to move the files?
                    if ($this->getPackageImages())
                    {   
                        $archive = $this->_getOutputDir() ."/done/";
                        if (!file_exists($archive)) {
                            if (!mkdir($archive, 0777, TRUE))
                            {
                                throw new Exception('Unable to create archive directory '. $newPath);
                            }
                        }
                        rename($file->getPathName(), $archive ."/". $fileName);
                    }
                    $this->_count++;


                }
                else {
                    throw new Exception('Unable to find any exif date');
                }
            }
        }

        // Now, do the zipage.
        echo 'Copied '. $this->_count .' images.'."\n";
        if ($this->getPackageImages())
        {
            $tar = "tar -cf package.tar ". $this->_getOutputDir() ."/done/*";
            exec($tar, $output, $returnCode);
            exec("gzip package.tar", $output, $returnCode);
            rename('package.tar.gz', $this->_getOutputDir() ."/package.tar.gz");
            echo 'Tar\'d and gziped the shizzle'."\n";
        }

        // Now, finished the log and exit cleanly
        fwrite($log, "Number of files written: ". $this->_count);
        
        exit(0);
    }
}
