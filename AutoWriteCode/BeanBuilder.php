<?php
/**
 * Created by PhpStorm.
 * User: eValor
 * Date: 2018/11/10
 * Time: 上午1:52
 */

namespace AutoWriteCode;

use AutoWriteCode\Config\BeanConfig;
use EasySwoole\Utility\Str;
use Nette\PhpGenerator\ClassType;
use Nette\PhpGenerator\PhpNamespace;

/**
 * easyswoole Bean快速构建器
 * Class BeanBuilder
 * @package AutoWriteCode
 */
class BeanBuilder
{
    /**
     * @var $config BeanConfig;
     */
    protected $config;

    /**
     * BeanBuilder constructor.
     * @param        $config
     * @throws \Exception
     */
    public function __construct(BeanConfig $config)
    {
        $this->config = $config;
        $this->createBaseDirectory($config->getBaseDirectory());
    }

    /**
     * createBaseDirectory
     * @param $baseDirectory
     * @throws \Exception
     * @author Tioncico
     * Time: 19:49
     */
    protected function createBaseDirectory($baseDirectory)
    {
        if (!is_dir((string)$baseDirectory)) {
            if (!@mkdir($baseDirectory, 0755)) throw new \Exception("Failed to create directory {$baseDirectory}");
            @chmod($baseDirectory, 0755);  // if umask
            if (!is_writable($baseDirectory)) {
                throw new \Exception("The directory {$baseDirectory} cannot be written. Please set the permissions manually");
            }
        }
    }

    /**
     * generateBean
     * @return bool|int
     * @author Tioncico
     * Time: 19:49
     */
    public function generateBean()
    {
        $realTableName = $this->setRealTableName() . 'Bean';

        $phpNamespace = new PhpNamespace($this->config->getBaseNamespace());
        $phpClass = $phpNamespace->addClass($realTableName);
        $phpClass->addExtend("EasySwoole\Spl\\SplBean");
        $phpClass->addComment("{$this->config->getTableComment()}");
        $phpClass->addComment("Class {$realTableName}");
        $phpClass->addComment('Create With Automatic Generator');
        foreach ($this->config->getTableColumns() as $column) {
            $name = $column['Field'];
            $comment = $column['Comment'];
            $columnType = $this->convertDbTypeToDocType($column['Type']);
            $phpClass->addComment("@property {$columnType} {$name} | {$comment}");
            $phpClass->addProperty($column['Field'])->setVisibility('protected');
            $this->addSetMethod($phpClass, $column['Field']);
            $this->addGetMethod($phpClass, $column['Field']);
        }
        return $this->createPHPDocument($this->config->getBaseDirectory() . '/' . $realTableName, $phpNamespace, $this->config->getTableColumns());
    }

    /**
     * 处理表真实名称
     * setRealTableName
     * @return bool|mixed|string
     * @author tioncico
     * Time: 下午11:55
     */
    function setRealTableName()
    {
        if ($this->config->getRealTableName()){
            return $this->config->getRealTableName();
        }
        //先去除前缀
        $tableName = substr($this->config->getTableName(), strlen($this->config->getTablePre()));
        //去除后缀
        $tableName = str_replace($this->config->getIgnoreString(),'',$tableName);
        //下划线转驼峰,并且首字母大写
        $tableName = ucfirst(Str::camel($tableName));
        $this->config->setRealTableName($tableName);
        return $tableName;
    }

    function addSetMethod(ClassType $phpClass, $column)
    {
        $method = $phpClass->addMethod("set" . Str::studly($column));
        $method->addParameter($column);
        $methodBody = <<<Body
\$this->$column = \$$column;
Body;
        //配置方法内容
        $method->setBody($methodBody);
    }

    function addGetMethod(ClassType $phpClass, $column)
    {
        $method = $phpClass->addMethod("get" . Str::studly($column));
        $methodBody = <<<Body
return \$this->$column;
Body;
        //配置方法内容
        $method->setBody($methodBody);
    }

    /**
     * convertDbTypeToDocType
     * @param $fieldType
     * @return string
     * @author Tioncico
     * Time: 19:49
     */
    protected function convertDbTypeToDocType($fieldType)
    {
        $newFieldType = strtolower(strstr($fieldType, '(', true));
        if ($newFieldType == '') $newFieldType = strtolower($fieldType);
        if (in_array($newFieldType, ['tinyint', 'smallint', 'mediumint', 'int', 'bigint'])) {
            $newFieldType = 'int';
        } elseif (in_array($newFieldType, ['float', 'double', 'real', 'decimal', 'numeric'])) {
            $newFieldType = 'float';
        } elseif (in_array($newFieldType, ['char', 'varchar', 'text'])) {
            $newFieldType = 'string';
        } else {
            $newFieldType = 'mixed';
        }
        return $newFieldType;
    }

    /**
     * createPHPDocument
     * @param $fileName
     * @param $fileContent
     * @param $tableColumns
     * @return bool|int
     * @author Tioncico
     * Time: 19:49
     */
    protected function createPHPDocument($fileName, $fileContent, $tableColumns)
    {
        if (file_exists($fileName . '.php')) {
            echo "(Bean)当前路径已经存在文件,是否覆盖?(y/n)\n";
            if (trim(fgets(STDIN)) == 'n') {
                echo "已结束运行";
                return false;
            }
        }
        $content = "<?php\n\n{$fileContent}\n";
        $result = file_put_contents($fileName . '.php', $content);

        return $result == false ? $result : $fileName . '.php';
    }

}