<?php
/**
 * Upload
 *
 * @author      Josh Lockhart <info@joshlockhart.com>
 * @copyright   2012 Josh Lockhart
 * @link        http://www.joshlockhart.com
 * @version     2.0.0
 *
 * MIT LICENSE
 *
 * Permission is hereby granted, free of charge, to any person obtaining
 * a copy of this software and associated documentation files (the
 * "Software"), to deal in the Software without restriction, including
 * without limitation the rights to use, copy, modify, merge, publish,
 * distribute, sublicense, and/or sell copies of the Software, and to
 * permit persons to whom the Software is furnished to do so, subject to
 * the following conditions:
 *
 * The above copyright notice and this permission notice shall be
 * included in all copies or substantial portions of the Software.
 *
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND,
 * EXPRESS OR IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF
 * MERCHANTABILITY, FITNESS FOR A PARTICULAR PURPOSE AND
 * NONINFRINGEMENT. IN NO EVENT SHALL THE AUTHORS OR COPYRIGHT HOLDERS BE
 * LIABLE FOR ANY CLAIM, DAMAGES OR OTHER LIABILITY, WHETHER IN AN ACTION
 * OF CONTRACT, TORT OR OTHERWISE, ARISING FROM, OUT OF OR IN CONNECTION
 * WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE SOFTWARE.
 */

namespace Upload;

/**
 * File
 *
 * This class provides the implementation for an uploaded file. It exposes
 * common attributes for the uploaded file (e.g. name, extension, media type)
 * and allows you to attach validations to the file that must pass for the
 * upload to succeed.
 *
 * @author  Josh Lockhart <info@joshlockhart.com>
 * @since   1.0.0
 * @package Upload
 */
class File implements \ArrayAccess, \IteratorAggregate, \Countable
{
    /**
     * Upload error code messages
     * @var array
     */
    protected static $errorCodeMessages = array(
        1 => 'The uploaded file exceeds the upload_max_filesize directive in php.ini',
        2 => 'The uploaded file exceeds the MAX_FILE_SIZE directive that was specified in the HTML form',
        3 => 'The uploaded file was only partially uploaded',
        4 => 'No file was uploaded',
        6 => 'Missing a temporary folder',
        7 => 'Failed to write file to disk',
        8 => 'A PHP extension stopped the file upload'
    );

    /**
     * Storage delegate
     * @var \Upload\StorageInterface
     */
    protected $storage;

    /**
     * File information
     * @var array[\Upload\FileInfoInterface]
     */
    protected $objects = array();

    /**
     * Validations
     * @var array[\Upload\ValidationInterface]
     */
    protected $validations = array();

    /**
     * Validation errors
     * @var array[String]
     */
    protected $errors = array();

    /**
     * Before validation callback
     * @var callable
     */
    protected $beforeValidation;

    /**
     * After validation callback
     * @var callable
     */
    protected $afterValidation;

    /**
     * Before upload callback
     * @var callable
     */
    protected $beforeUpload;

    /**
     * After upload callback
     * @var callable
     */
    protected $afterUpload;

    /**
     * File constructor.
     * @param string $key
     * @param StorageInterface $storage
     * @param array $files
     */
    public function __construct(string $key, \Upload\StorageInterface $storage, array $files = [])
    {
        // Check if file uploads are allowed
        if (ini_get('file_uploads') == false) {
            throw new \RuntimeException('File uploads are disabled in your PHP.ini file');
        }
        $files = empty($files) ? $_FILES : $files;

        // Check if key exists
        if (isset($files[$key]) === false) {
            throw new \InvalidArgumentException("Cannot find uploaded file(s) identified by key: $key");
        }

        // Collect file info
        if (is_array($files[$key]['tmp_name']) === true) {
            foreach ($files[$key]['tmp_name'] as $index => $tmpName) {
                if ($files[$key]['error'][$index] !== UPLOAD_ERR_OK) {
                    $this->errors[] = sprintf(
                        '%s: %s',
                        $files[$key]['name'][$index],
                        static::$errorCodeMessages[$files[$key]['error'][$index]]
                    );
                    continue;
                }

                $this->objects[] = \Upload\FileInfo::createFromFactory(
                    $files[$key]['tmp_name'][$index],
                    $files[$key]['name'][$index]
                );
            }
        } else {
            if ($files[$key]['error'] !== UPLOAD_ERR_OK) {
                $this->errors[] = sprintf(
                    '%s: %s',
                    $files[$key]['name'],
                    static::$errorCodeMessages[$files[$key]['error']]
                );
            }

            $this->objects[] = \Upload\FileInfo::createFromFactory(
                $files[$key]['tmp_name'],
                $files[$key]['name']
            );
        }

        $this->storage = $storage;
    }

    /********************************************************************************
     * Callbacks
     *******************************************************************************/

    /**
     * Set `beforeValidation` callable
     * @param callable $callable
     * @return File
     */
    public function beforeValidate(callable $callable): self
    {
        $this->beforeValidation = $callable;
        return $this;
    }

    /**
     * Set `afterValidation` callable
     * @param callable $callable
     * @return File
     */
    public function afterValidate(callable $callable): self
    {
        $this->afterValidation = $callable;
        return $this;
    }

    /**
     * Set `beforeUpload` callable
     * @param callable $callable
     * @return File
     */
    public function beforeUpload(callable $callable): self
    {
        $this->beforeUpload = $callable;
        return $this;
    }

    /**
     * Set `afterUpload` callable
     * @param callable $callable
     * @return File
     */
    public function afterUpload(callable $callable): self
    {
        $this->afterUpload = $callable;
        return $this;
    }

    /**
     * Apply callable
     * @param string $callbackName
     * @param FileInfoInterface $file
     */
    protected function applyCallback(string $callbackName, \Upload\FileInfoInterface $file)
    {
        if (in_array($callbackName, array('beforeValidation', 'afterValidation', 'beforeUpload', 'afterUpload')) === true) {
            if (isset($this->$callbackName) === true) {
                call_user_func_array($this->$callbackName, array($file)); 
            }
        }
    }

    /********************************************************************************
     * Validation and Error Handling
     *******************************************************************************/

    /**
     * Add file validations
     * @param array $validations
     * @return File
     */
    public function addValidations(array $validations): self
    {
        foreach ($validations as $validation) {
            $this->addValidation($validation);
        }
        return $this;
    }

    /**
     * Add file validation
     * @param ValidationInterface $validation
     * @return File
     */
    public function addValidation(\Upload\ValidationInterface $validation): self
    {
        $this->validations[] = $validation;

        return $this;
    }

    /**
     * Get file validations
     * @return array
     */
    public function getValidations(): array
    {
        return $this->validations;
    }

    /**
     * Is this collection valid and without errors?
     *
     * @return bool
     */
    public function isValid()
    {
        foreach ($this->objects as $fileInfo) {
            // Before validation callback
            $this->applyCallback('beforeValidation', $fileInfo);

            // Check is uploaded file
            if ($fileInfo->isUploadedFile() === false) {
                $this->errors[] = sprintf(
                    '%s: %s',
                    $fileInfo->getNameWithExtension(),
                    'Is not an uploaded file'
                );
                continue;
            }

            // Apply user validations
            foreach ($this->validations as $validation) {
                try {
                    $validation->validate($fileInfo);
                } catch (\Upload\Exception $e) {
                    $this->errors[] = sprintf(
                        '%s: %s',
                        $fileInfo->getNameWithExtension(),
                        $e->getMessage()
                    );
                }
            }

            // After validation callback
            $this->applyCallback('afterValidation', $fileInfo);
        }

        return empty($this->errors);
    }

    /**
     * Get file validation errors
     *
     * @return array[String]
     */
    public function getErrors()
    {
        return $this->errors;
    }

    /********************************************************************************
     * Helper Methods
     *******************************************************************************/

    public function __call($name, $arguments)
    {
        $count = count($this->objects);
        $result = null;

        if ($count) {
            if ($count > 1) {
                $result = array();
                foreach ($this->objects as $object) {
                    $result[] = call_user_func_array(array($object, $name), $arguments);
                }
            } else {
                $result = call_user_func_array(array($this->objects[0], $name), $arguments);
            }
        }

        return $result;
    }

    /********************************************************************************
     * Upload
     *******************************************************************************/

    /**
     * Upload file (delegated to storage object)
     * @return bool
     * @throws \Exception
     */
    public function upload(): bool
    {
        if ($this->isValid() === false) {
            throw new \Upload\Exception('File validation failed');
        }

        foreach ($this->objects as $fileInfo) {
            $this->applyCallback('beforeUpload', $fileInfo);
            $this->storage->upload($fileInfo);
            $this->applyCallback('afterUpload', $fileInfo);
        }

        return true;
    }

    /********************************************************************************
     * Array Access Interface
     *******************************************************************************/

    public function offsetExists($offset)
    {
        return isset($this->objects[$offset]);
    }

    public function offsetGet($offset)
    {
        return isset($this->objects[$offset]) ? $this->objects[$offset] : null;
    }

    public function offsetSet($offset, $value)
    {
        $this->objects[$offset] = $value;
    }

    public function offsetUnset($offset)
    {
        unset($this->objects[$offset]);
    }

    /********************************************************************************
     * Iterator Aggregate Interface
     *******************************************************************************/

    public function getIterator()
    {
        return new \ArrayIterator($this->objects);
    }

    /********************************************************************************
     * Countable Interface
     *******************************************************************************/

    public function count()
    {
        return count($this->objects);
    }

    /********************************************************************************
     * Helpers
     *******************************************************************************/

    /**
     * Convert human readable file size (e.g. "10K" or "3M") into bytes
     *
     * @param  string $input
     * @return int
     */
    public static function humanReadableToBytes($input)
    {
        $number = (int)$input;
        $units = array(
            'b' => 1,
            'k' => 1024,
            'm' => 1048576,
            'g' => 1073741824
        );
        $unit = strtolower(substr($input, -1));
        if (isset($units[$unit])) {
            $number = $number * $units[$unit];
        }

        return $number;
    }
}
