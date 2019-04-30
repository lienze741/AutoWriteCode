<?php
/**
 * Created by PhpStorm.
 * User: Tioncico
 * Date: 2019/4/14 0014
 * Time: 22:28
 */

namespace AutoWriteCode;

use App\Model\BaseModel;
use AutoWriteCode\Config\BeanConfig;
use AutoWriteCode\Config\ModelConfig;
use mysql_xdevapi\Exception;

class TableAutomatic
{
    const APP_PATH = 'Application';
    protected $tableName;
    protected $tablePre;
    protected $baseDir;
    protected $namespace;
    protected $tableColumns;
    protected $tableComment;

    public function __construct($tableName, $tablePre, $baseDir = null, $namespace = null)
    {
        $this->tableName = $tableName;
        $this->tablePre = $tablePre;
        $this->baseDir = $baseDir ?? EASYSWOOLE_ROOT . '/' . self::APP_PATH . '/Model';
        $this->namespace = $namespace ?? 'App\Model';
        $this->initTableInfo();
    }
    function initTableInfo(){
        $db = \App\Utility\Pool\MysqlPool::defer();
        $mysqlTable = new MysqlTable($db, \EasySwoole\EasySwoole\Config::getInstance()->getConf('MYSQL.database'));
        $tableName = $this->tableName;
        $tableColumns = $mysqlTable->getColumnList($tableName);
        $tableComment = $mysqlTable->getComment($tableName);
        if (empty($tableColumns)){
            throw new \Exception("{$tableName}表不存在");
        }
        $this->tableColumns = $tableColumns;
        $this->tableComment = $tableComment;
    }

    function action($generateBean = true, $generateModel = true, $generateController = true)
    {

        if ($generateBean) {
            $result = $this->generateBean($this->tableColumns, $this->tableComment);
            if ($result) {
                echo "生成[{$result}]成功\n";
            } else {
                echo "生成bean失败";
            }
        }
        if ($generateModel) {
            $result = $this->generateModel($this->tableColumns, $this->tableComment, BaseModel::class);
            if ($result) {
                echo "生成[{$result}]成功\n";
            } else {
                echo "生成Model失败";
            }
        }
        if ($generateController) {
//                $result = $this->generateBean($tableColumns,$tableComment);
//                if ($result){
//                    echo "生成[{$result}]成功\n";
//                }else{
//                    echo "生成bean失败";
//                }
        }

        exit;
    }

    function generateModel($tableColumns, $tableComment, $extendClass = null)
    {
        $modelConfig = new ModelConfig();
        $modelConfig->setBaseDirectory($this->baseDir);
        $modelConfig->setBaseNamespace($this->namespace);
        $modelConfig->setTablePre($this->tablePre);
        $modelConfig->setExtendClass(BaseModel::class);
        $modelBuilder = new ModelBuilder($modelConfig);
        return $modelBuilder->generateModel($this->tableName, $tableComment, $tableColumns);
    }

    function generateBean($tableColumns, $tableComment)
    {
        $beanConfig = new BeanConfig();
        $beanConfig->setBaseDirectory($this->baseDir);
        $beanConfig->setBaseNamespace($this->namespace);
        $beanConfig->setTablePre($this->tablePre);
        $beanBuilder = new BeanBuilder($beanConfig);
        return $beanBuilder->generateBean($this->tableName, $tableComment, $tableColumns);
    }

}
