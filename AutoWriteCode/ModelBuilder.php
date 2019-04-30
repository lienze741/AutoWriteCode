<?php
/**
 * Created by PhpStorm.
 * User: eValor
 * Date: 2018/11/10
 * Time: 上午1:52
 */

namespace AutoWriteCode;

use AutoWriteCode\Config\ModelConfig;
use EasySwoole\Utility\Str;
use Nette\PhpGenerator\ClassType;
use Nette\PhpGenerator\PhpNamespace;

/**
 * easyswoole model快速构建器
 * Class BeanBuilder
 * @package AutoWriteCode
 */
class ModelBuilder
{
    /**
     * @var $config ModelConfig
     */
    protected $config;

    /**
     * BeanBuilder constructor.
     * @param  $config
     * @throws \Exception
     */
    public function __construct(ModelConfig $config)
    {
        $this->config=$config;
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
     * @param $tableName
     * @param $tableComment
     * @param $tableColumns
     * @return bool|int
     * @author Tioncico
     * Time: 19:49
     */
    public function generateModel($tableName, $tableComment, $tableColumns)
    {
        $phpNamespace = new PhpNamespace($this->config->getBaseNamespace());
        $realTableName = ucfirst(Str::camel(substr($tableName, strlen($this->config->getTablePre())))) . 'Model';
        $phpClass = $this->addClassBaseContent($tableName, $realTableName, $phpNamespace, $tableComment, $tableColumns);
        //配置getAll
        $this->addGetAllMethod($phpClass);
        $this->addGetOneMethod($phpClass, $tableName, $tableColumns);
        $this->addAddMethod($phpClass, $tableName, $tableColumns);
        $this->addDeleteMethod($phpClass, $tableName, $tableColumns);
        $this->addUpdateMethod($phpClass, $tableName, $tableColumns);

        return $this->createPHPDocument($this->config->getBaseDirectory() . '/' . $realTableName, $phpNamespace, $tableColumns);
    }

    /**
     * 新增基础类内容
     * addClassBaseContent
     * @param $tableName
     * @param $realTableName
     * @param $phpNamespace
     * @param $tableComment
     * @return ClassType
     * @author Tioncico
     * Time: 21:38
     */
    protected function addClassBaseContent($tableName, $realTableName, $phpNamespace, $tableComment, $tableColumns): ClassType
    {
        $phpClass = $phpNamespace->addClass($realTableName);
        //配置类基本信息
        if ($this->config->getExtendClass()) {
            $phpClass->addExtend($this->config->getExtendClass());
        }
        $phpClass->addComment("{$tableComment}");
        $phpClass->addComment("Class {$realTableName}");
        $phpClass->addComment('Create With Automatic Generator');
        //配置表名属性
        $phpClass->addProperty('table', $tableName)
            ->setVisibility('protected');
        foreach ($tableColumns as $column) {
            if ($column['Key'] == 'PRI') {
                $this->config->setPrimaryKey($column['Field']);
                $phpClass->addProperty('primaryKey', $column['Field'])
                    ->setVisibility('protected');
                break;
            }
        }
        return $phpClass;
    }

    protected function addUpdateMethod(ClassType $phpClass, $tableName, $tableColumns)
    {
        $method = $phpClass->addMethod('update');
        $beanName = ucfirst(Str::camel(substr($tableName, strlen($this->config->getTablePre())))) . 'Bean';
        $namespaceBeanName = $this->config->getBaseNamespace() . '\\' . $beanName;
        //配置基础注释
        $method->addComment("默认根据主键({$this->config->getPrimaryKey()})进行更新");
        $method->addComment("@delete");
        $method->addComment("@param  {$beanName} \$bean");//默认为使用Bean注释
        $method->addComment("@param  array \$data");

        //配置返回类型
        $method->setReturnType('bool');
        //配置参数为bean
        $method->addParameter('bean')->setTypeHint($namespaceBeanName);
        $method->addParameter('data')->setTypeHint('array');
        $getPrimaryKeyMethodName = "get" . Str::studly($this->config->getPrimaryKey());

        $methodBody = <<<Body
if (empty(\$data)){
    return false;
}
return \$this->getDbConnection()->where(\$this->primaryKey, \$bean->$getPrimaryKeyMethodName())->update(\$this->table, \$data);
Body;
        $method->setBody($methodBody);
        $method->addComment("@return bool");
    }

    protected function addDeleteMethod(ClassType $phpClass, $tableName, $tableColumns)
    {
        $method = $phpClass->addMethod('delete');
        $beanName = ucfirst(Str::camel(substr($tableName, strlen($this->config->getTablePre())))) . 'Bean';
        $namespaceBeanName = $this->config->getBaseNamespace() . '\\' . $beanName;
        //配置基础注释
        $method->addComment("默认根据主键({$this->config->getPrimaryKey()})进行删除");
        $method->addComment("@delete");
        $method->addComment("@param  {$beanName} \$bean");//默认为使用Bean注释

        //配置返回类型
        $method->setReturnType('bool');
        //配置参数为bean
        $method->addParameter('bean')->setTypeHint($namespaceBeanName);
        $getPrimaryKeyMethodName = "get" . Str::studly($this->config->getPrimaryKey());

        $methodBody = <<<Body
return  \$this->getDbConnection()->where(\$this->primaryKey, \$bean->$getPrimaryKeyMethodName())->delete(\$this->table);
Body;
        $method->setBody($methodBody);
        $method->addComment("@return bool");
    }

    protected function addAddMethod(ClassType $phpClass, $tableName, $tableColumns)
    {
        $method = $phpClass->addMethod('add');
        $beanName = ucfirst(Str::camel(substr($tableName, strlen($this->config->getTablePre())))) . 'Bean';
        $namespaceBeanName = $this->config->getBaseNamespace() . '\\' . $beanName;
        //配置基础注释
        $method->addComment("默认根据bean数据进行插入数据");
        $method->addComment("@add");
        $method->addComment("@param  {$beanName} \$bean");//默认为使用Bean注释
        //配置参数为bean
        $method->addParameter('bean')->setTypeHint($namespaceBeanName);
        //配置返回类型
        $method->setReturnType('bool');

        $methodBody = <<<Body
return \$this->getDbConnection()->insert(\$this->table, \$bean->toArray(null, \$bean::FILTER_NOT_NULL));
Body;
        $method->setBody($methodBody);
        $method->addComment("@return bool");
    }

    protected function addGetOneMethod(ClassType $phpClass, $tableName, $tableColumns)
    {
        $method = $phpClass->addMethod('getOne');
        $beanName = ucfirst(Str::camel(substr($tableName, strlen($this->config->getTablePre())))) . 'Bean';
        $namespaceBeanName = $this->config->getBaseNamespace() . '\\' . $beanName;
        //配置基础注释
        $method->addComment("默认根据主键({$this->config->getPrimaryKey()})进行搜索");
        $method->addComment("@getOne");
        $method->addComment("@param  {$beanName} \$bean");//默认为使用Bean注释

        //配置返回类型
        $method->setReturnType($namespaceBeanName)->setReturnNullable();
        //配置参数为bean
        $method->addParameter('bean')->setTypeHint($namespaceBeanName);
        $getPrimaryKeyMethodName = "get" . Str::studly($this->config->getPrimaryKey());

        $methodBody = <<<Body
\$info = \$this->getDbConnection()->where(\$this->primaryKey, \$bean->$getPrimaryKeyMethodName())->getOne(\$this->table);
if (empty(\$info)) {
    return null;
}
return new $beanName(\$info);
Body;
        $method->setBody($methodBody);
        $method->addComment("@return $beanName");
    }

    protected function addGetAllMethod(ClassType $phpClass, $keyword = '')
    {
        $method = $phpClass->addMethod('getAll');
        if (empty($keyword)) {
            echo "(getAll)请输入搜索的关键字\n";
            $keyword = trim(fgets(STDIN));
        }
        //配置基础注释
        $method->addComment("@getAll");
        if (!empty($keyword)) {
            $method->addComment("@keyword $keyword");
        }
        //配置方法参数
        $method->addParameter('page', 1)
            ->setTypeHint('int');
        $method->addParameter('keyword', null)
            ->setTypeHint('string');
        $method->addParameter('pageSize', 10)
            ->setTypeHint('int');
        foreach ($method->getParameters() as $parameter) {
            $method->addComment("@param  " . $parameter->getTypeHint() . '  ' . $parameter->getName() . '  ' . $parameter->getDefaultValue());
        }

        //配置返回类型
        $method->setReturnType('array');

        $methodBody = <<<Body
if (!empty(\$keyword)) {
    \$this->getDbConnection()->where('$keyword', '%' . \$keyword . '%', 'like');
}

\$list = \$this->getDbConnection()
    ->withTotalCount()
    ->orderBy(\$this->primaryKey, 'DESC')
    ->get(\$this->table, [\$pageSize * (\$page  - 1), \$pageSize]);
\$total = \$this->getDbConnection()->getTotalCount();
return ['total' => \$total, 'list' => \$list];
Body;
        //配置方法内容
        $method->setBody($methodBody);
        $method->addComment('@return array[total,list]');
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
//        var_dump($fileName.'.php');
        if (file_exists($fileName . '.php')) {
            echo "(Model)当前路径已经存在文件,是否覆盖?(y/n)\n";
            if (trim(fgets(STDIN)) == 'n') {
                echo "已结束运行";
                return false;
            }
        }
        $content = "<?php\n\n{$fileContent}\n";
//        var_dump($content);
        $result = file_put_contents($fileName . '.php', $content);
        return $result == false ? $result : $fileName . '.php';
    }
}