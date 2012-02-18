<?php
/*
 *      PHP Datei.php
 *      
 *      Copyright 2009 Richard Liebscher <richard.liebscher@googlemail.com>
 *      
 *      This program is free software; you can redistribute it and/or modify
 *      it under the terms of the GNU General Public License as published by
 *      the Free Software Foundation; either version 2 of the License, or
 *      (at your option) any later version.
 *      
 *      This program is distributed in the hope that it will be useful,
 *      but WITHOUT ANY WARRANTY; without even the implied warranty of
 *      MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 *      GNU General Public License for more details.
 *      
 *      You should have received a copy of the GNU General Public License
 *      along with this program; if not, write to the Free Software
 *      Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston,
 *      MA 02110-1301, USA.
 */
 
define("BUFFER_SIZE", 4096);
define("PERMISSIONS", 0777);

/**
 * Create a directory structure recursively
 *
 * @author      Aidan Lister <aidan@php.net>
 * @version     1.0.2
 * @link        http://aidanlister.com/2004/04/recursively-creating-directory-structures/
 * @param       string   $pathname    The directory structure to create
 * @return      bool     Returns TRUE on success, FALSE on failure
 */
function mkdirr($pathname, $mode = PERMISSIONS)
{
    // Check if directory already exists
    if (is_dir($pathname) || empty($pathname)) {
        return true;
    }

    // Ensure a file does not already exist with the same name
    $pathname = str_replace(array('/', ''), DIRECTORY_SEPARATOR, $pathname);
    if (is_file($pathname)) {
        trigger_error('mkdirr() File exists', E_USER_WARNING);
        return false;
    }

    // Crawl up the directory tree
    $next_pathname = substr($pathname, 0, strrpos($pathname, DIRECTORY_SEPARATOR));
    if (mkdirr($next_pathname, $mode)) {
        if (!file_exists($pathname)) {
            return mkdir($pathname, $mode);
        }
    }

    return false;
}


////////////////////////////////////////////////////////////////////////////////
// RAR
////////////////////////////////////////////////////////////////////////////////
/*function unrar($file) {
  $rar = RarArchive::open($file);

  foreach($rar->getEntries() as $entry) {
    $entry->extract(''); // TODO: password?
  }
  
  $rar->close();
}*/

////////////////////////////////////////////////////////////////////////////////
// TAR
////////////////////////////////////////////////////////////////////////////////
function untar($file) {
  include('../libs/pclerror.lib.php3');
  include('../libs/pcltrace.lib.php3');
  include('../libs/pcltar.lib.php3');

  PclTarExtract($file); // TODO: Zugriffsrechte ändern
}

////////////////////////////////////////////////////////////////////////////////
// BZIP2
////////////////////////////////////////////////////////////////////////////////
/*function bzip2 ($in, $out, $param="1")
{
    if (!file_exists ($in) || !is_readable ($in))
        return false;
    if ((!file_exists ($out) && !is_writable (dirname ($out)) || (file_exists($out) && !is_writable($out)) ))
        return false;
    
    $in_file = fopen ($in, "rb");
    if (!$out_file = bzopen ($out, "wb".$param)) {
        return false;
    }
    
    while (!feof ($in_file)) {
        $buffer = fgets ($in_file, 4096);
        gzwrite ($out_file, $buffer, 4096);
    }

    fclose ($in_file);
    gzclose ($out_file);
    
    return true;
}*/

function bunzip2($file) {
  $dest = pathinfo($file, PATHINFO_FILENAME);
  lputs("Unpacking ".$dest);  
  if (file_exists($dest) && !is_writable($dest))
    lerror("$dest nicht schreibbar");

  $ext = pathinfo($dest, PATHINFO_EXTENSION);
  if ($ext == 'tar') {
    $dest = tempnam(sys_get_temp_dir(), pathinfo($dest, PATHINFO_FILENAME)).'.tar';
  }

  $bf = bzopen($file, "r");
  $f = fopen($dest, "wb");
  
  is_resource($bf) or lerror("Unable to open $file");
  is_resource($f) or lerror("Unable to open $dest");

  while (!feof($bf)) {
    $bytes = fwrite($f, bzread($bf, BUFFER_SIZE), BUFFER_SIZE);
    ($bytes !== FALSE) or lerror('Schreibfehler');
  }

  bzclose($bf);
  fclose($f);
 
  if ($ext == 'tar') {
    untar($dest);
    unlink($dest);
  } else {
    chmod($dest, PERMISSIONS);
  } 
  
  return true;
}

////////////////////////////////////////////////////////////////////////////////
// GZIP
////////////////////////////////////////////////////////////////////////////////
/*function gzip ($in, $out, $param="1")
{
    if (!file_exists ($in) || !is_readable ($in))
        return false;
    if ((!file_exists ($out) && !is_writable (dirname ($out)) || (file_exists($out) && !is_writable($out)) ))
        return false;
    
    $in_file = fopen ($in, "rb");
    if (!$out_file = gzopen ($out, "wb".$param)) {
        return false;
    }
    
    while (!feof ($in_file)) {
        $buffer = fgets ($in_file, 4096);
        gzwrite ($out_file, $buffer, 4096);
    }

    fclose ($in_file);
    gzclose ($out_file);
    
    return true;
}*/

function gunzip($file) {
  $dest = pathinfo($file, PATHINFO_FILENAME);
  lputs("Unpacking ".$dest);  
  if (file_exists($dest) && !is_writable($dest))
    lerror("$dest nicht schreibbar");

  $ext = pathinfo($dest, PATHINFO_EXTENSION);
  if ($ext == 'tar') {
    $dest = tempnam(sys_get_temp_dir(), pathinfo($dest, PATHINFO_FILENAME)).'.tar';
  }

  $gf = gzopen($file, "rb");
  $f = fopen($dest, "wb");
  
  is_resource($gf) or lerror("Unable to open $file");
  is_resource($f) or lerror("Unable to open $dest");

  while (!gzeof($gf)) {
    $bytes = fwrite($f, gzread($gf, BUFFER_SIZE), BUFFER_SIZE);
    ($bytes !== FALSE) or lerror('Schreibfehler');
  }

  gzclose($gf);
  fclose($f);
  
  if ($ext == 'tar') {
    untar($dest);
    unlink($dest);
  } else {
    chmod($dest, PERMISSIONS);
  } 
  
  return true;
}

////////////////////////////////////////////////////////////////////////////////
// ZIP
////////////////////////////////////////////////////////////////////////////////
function zip_unpack_file($zip_entry, $file) {
  if (strlen(trim(basename($file))) == 0) {
    return true; 
  }
  
  $size = zip_entry_filesize($zip_entry);
  $f = fopen($file, "wb");
  if (is_resource($f)) {
    while ($size > 0) {
      $read = min($size, BUFFER_SIZE);
      $buffer = zip_entry_read($zip_entry, $read);
      if ($buffer !== false) {
        fwrite($f, $buffer);
      } else {
        fclose($f);
        return false;
      }
      $size -= $read;
    }
    fclose($f);
    chmod($file, PERMISSIONS);
    return true;
  } else {
    return false; 
  }
}

function unzip($file){
  $zip = zip_open($file);
  if (is_resource($zip)) {
    $tree = "";
    while (($zip_entry = zip_read($zip)) !== false) {
      $name = zip_entry_name($zip_entry);
      lputs("Unpacking ".$name);
      zip_entry_open($zip, $zip_entry) or lerror("Unable to read $name");      
      if (strpos(zip_entry_name($zip_entry), DIRECTORY_SEPARATOR) !== false) {
        $dir = dirname($name);
        if (!is_dir($dir)) {
          @mkdirr($dir, PERMISSIONS, true) or lerror("Unable to create $dir");
        }
      }
      zip_unpack_file($zip_entry, $name) or lerror("Unable to unpack $name");
      zip_entry_close($zip_entry);
    }    
    zip_close($zip);
  } else {
    lerror("Unable to open zip file");
  }
} 

////////////////////////////////////////////////////////////////////////////////

$opt = parse_cmdln($args);
if (!array_key_exists(0, $opt)) {
  $opt[0] = '.';
}
$file = &$opt[0];

is_file($file) or lerror($file.' ist keine gültige Datei');
is_readable($file) or lerror($file.' ist nicht lesbar');
is_writable(getcwd()) or lerror(getcwd().' ist nicht schreibbar');
  
$ext = pathinfo($file, PATHINFO_EXTENSION);
switch ($ext) {
case 'rar':
  unrar($file);
  break;

case 'zip':
  unzip($file);
  break;
  
case 'gz':
case 'gzip':
  gunzip($file);
  break;
  
case 'bz2':
case 'bzip2':
  bunzip2($file);
  break;
  
case 'tgz':
case 'tar':
  untar($file);
  break;
  
default:
  lerror($file.' benutzt nicht unterstützte Komprimierung');
}


?>