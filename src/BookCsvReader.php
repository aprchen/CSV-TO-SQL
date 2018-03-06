<?php
/**
 * Created by PhpStorm.
 * User: sl
 * Date: 2018/3/6
 * Time: 上午11:43
 */

namespace App;

use Aprchen\CsvHelper\Charset\CharsetHelper;
use Aprchen\CsvHelper\Constants\CharsetType;
use Aprchen\CsvHelper\Constants\FileMode;
use Aprchen\CsvHelper\FileFactory;
use Aprchen\CsvHelper\Reader;

class BookCsvReader extends Reader
{

    /**
     * @throws \Exception
     */
    public function run()
    {
        $file = $this->getFile();
        $dir = OUTPUT;
        $helper = new CharsetHelper();
        $fileEncoding = $helper->getEncoding($file);
        if(!$fileEncoding){
            return false;
        }
        $bom = $helper->hasBOM();
        if($bom){
            rename($file,$file."_BOM.bak"); //有BOM头,不处理
        }
        $bookId = $this->getBookId();
        $time = time();
        $isDeleted = 0;
        $records = $this->getContent();
        if ($records) {
            $articleFile = FileFactory::createFileWithPath($dir."/article_${bookId}.sql",FileMode::HEAD_WRITE_CREATE);
            $article = new SqlWriter($articleFile);
            $article
                ->setTable("sl_book_article")
                ->setFields(['`book_id`','`chapter_no`','`title`','`content`','`is_deleted`','`gmt_create`','`gmt_modified`'])
                ->insertBom()
                ->begin()
                ->clearData("`book_id`= ${bookId}");
            $chapterFile = FileFactory::createFileWithPath($dir."/chapters_${bookId}.sql",FileMode::HEAD_WRITE_CREATE);
            $chapter = new SqlWriter($chapterFile);
            $chapter
                ->setTable("sl_book_chapter")
                ->setFields(['`book_id`','`chapter_no`','`name`','`article_id`','`price`','`is_expense`','`is_attention`','`is_preview`','`is_deleted`','`gmt_create`','`gmt_modified`'])
                ->insertBom()
                ->begin()
                ->clearData("`book_id`= ${bookId}");
            foreach ($records as $key => $record) {
                if ($key == 0) { //排除 header
                    continue;
                }
                list($title, $content,$no) = $record;
                $title = $helper->setEncoding($title, $fileEncoding, CharsetType::UTF8);
                $title = $this->filter($title);
                $content = $helper->setEncoding($content, $fileEncoding, CharsetType::UTF8);
                $content = $this->filter($content);
                $no = (int)$no ?? 0;
                $articleSql = "('${bookId}','${no}','${title}','${content}','${isDeleted}',${time},${time});\n";
                $article->insert($articleSql);
                if($key>0 && $key<=20){
                    $price = 0;
                    $isAttention = 0;
                    $isExpense = 0;
                    $isPreview = 0;
                }else{
                    $price = 20;
                    $isAttention = 1;
                    $isExpense = 1;
                    $isPreview = 1;
                }
                $chapterSql = "('${bookId}','${no}','${title}','0','${price}','${isExpense}','${isAttention}','${isPreview}','${isDeleted}',${time},${time});\n";
                $chapter->insert($chapterSql);
            }
            $article->commit();
            $chapter->commit();
        }
        rename($file,$file.".bak"); //处理完成的数据重新命名,防止再次读取
        unset($file);
        unset($fileEncoding);
        unset($helper);
    }


    protected function getBookId(){
        $name = $this->file->getFilename();
        $res = explode('_',$name);
        return end($res);
    }

    protected function filter($text){
        $text = str_replace(["\n","\r"], "<br/>", $text);
        return addslashes($text);  //转义 ',",\,null
    }

}