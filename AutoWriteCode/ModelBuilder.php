<?php
/**
 * Created by PhpStorm.
 * User: eValor
 * Date: 2018/11/10
 * Time: 上午1:52
 */

namespace AutoWriteCode;

use EasySwoole\Utility\Str;
use http\Message\Body;
use Nette\PhpGenerator\ClassType;
use Nette\PhpGenerator\PhpNamespace;

/**
 * easyswoole model快速构建器
 * Class BeanBuilder
 * @package AutoWriteCode
 */
class ModelBuilder
{
    protected $basePath;
    protected $nameType = 1;
    protected $baseNamespace;
    protected $extendClass;
    protected $tablePre = '';
    protected $primaryKey;

    /**
     * BeanBuilder constructor.
     * @param        $baseDirectory
     * @param        $baseNamespace
     * @param string $tablePre
     * @throws \Exception
     */
    public function __construct($baseDirectory, $baseNamespace, $extendClass, $tablePre = '')
    {
        $this->basePath = $baseDirectory;
        $this->createBaseDirectory($baseDirectory);
        $this->baseNamespace = $baseNamespace;
        $this->extendClass = $extendClass;
        $this->tablePre = $tablePre;
    }

    public function setPrimaryKey($primaryKey)
    {
        $this->primaryKey = $primaryKey;
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
        $phpNamespace = new PhpNamespace($this->baseNamespace);
        $realTableName = ucfirst(Str::camel(substr($tableName, strlen($this->tablePre)))) . 'Model';
        $phpClass = $this->addClassBaseContent($tableName, $realTableName, $phpNamespace, $tableComment, $tableColumns);
        //配置getAll
        $this->addGetAllMethod($phpClass);
        $this->addGetOneMethod($phpClass,$tableName,$tableColumns);


        return $this->createPHPDocument($this->basePath . '/' . $realTableName, $phpNamespace, $tableColumns);
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
        $phpClass->addExtend($this->extendClass);
        $phpClass->addComment("{$tableComment}");
        $phpClass->addComment("Class {$realTableName}");
        $phpClass->addComment('Create With Automatic Generator');
        //配置表名属性
        $phpClass->addProperty('table', $tableName)
            ->setVisibility('protected');
        foreach ($tableColumns as $column) {
            if ($column['Key'] == 'PRI') {
                $this->primaryKey = $column['Field'];
                $phpClass->addProperty('primaryKey', $column['Field'])
                    ->setVisibility('protected');
                break;
            }
        }
        return $phpClass;
    }

    protected function addGetOneMethod(ClassType $phpClass,$tableName,$tableColumns){
        $method = $phpClass->addMethod('getOne');
        $beanName = ucfirst(Str::camel(substr($tableName, strlen($this->tablePre)))).'Bean';
        $beanName = $this->baseNamespace.'\\'.$beanName;
        //配置基础注释
        $method->addComment("默认根据主键({$this->primaryKey})进行搜索");
        $method->addComment("@getOne");
        $method->addComment("@param  {$beanName}" );//默认为使用Bean注释

        //配置返回类型
        $method->setReturnType($beanName)->setReturnNullable();
        //配置参数为bean
        $method->addParameter('bean')->setTypeHint($beanName);

        /*
         * 以customerCaseId进行获取
         */
//        function getOne(CustomerCaseBean $customerCaseBean):?CustomerCaseBean
//        {
//            $customerCase = $this->getDbConnection()->where($this->primaryKey, $customerCaseBean->getCustomerCaseId())->getOne($this->table);
//            if (empty($customerCase)) {
//                return null;
//            }
//            return new CustomerCaseBean($customerCase);
//        }


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
//        if (file_exists($fileName.'.php')){
//            echo "当前路径已经存在文件,是否覆盖?(y/n)\n";
//            if (trim(fgets(STDIN))=='n'){
//                echo "已结束运行";
//                return false;
//            }
//        }
        $fileContent = $this->publicPropertyToProtected($fileContent, $tableColumns);
        $fileContent = $this->addGetMethodContent($fileContent, $tableColumns);
        $content = "<?php\n\n{$fileContent}\n";
        return file_put_contents($fileName . '.php', $content);
    }

    /**
     * publicPropertyToProtected
     * @param $fileContent
     * @param $tableColumns
     * @return mixed
     * @author Tioncico
     * Time: 19:50
     */
    protected function publicPropertyToProtected($fileContent, $tableColumns)
    {
        foreach ($tableColumns as $column) {
            $fileContent = str_replace("public $" . $column['Field'] . ";", "protected $" . $column['Field'] . ";", $fileContent);
        }
        return $fileContent;
    }

    /**
     * addGetMethodContent
     * @param $fileContent
     * @param $tableColumns
     * @return string|string[]|null
     * @author Tioncico
     * Time: 19:50
     */
    protected function addGetMethodContent($fileContent, $tableColumns)
    {
        foreach ($tableColumns as $column) {
            $pattern = '/(public\\s+function\\s+set' . Str::studly($column['Field']) . ')\(\)\\s+({)(\\s*)(})/';
            $fileContent = preg_replace($pattern, '$1($' . Str::camel($column['Field']) . ')$2$this->' . $column['Field'] . '=$' . Str::camel($column['Field']) . ';$4', $fileContent);
            $pattern = '/(public\\s+function\\s+get' . Str::studly($column['Field']) . ')\(\)\\s+({)(\\s*)(})/';
            $fileContent = preg_replace($pattern, '$1()$2 return $this->' . $column['Field'] . ';$4', $fileContent);
        }
        return $fileContent;
    }
}