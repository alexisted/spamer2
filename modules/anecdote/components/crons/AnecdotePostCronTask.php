<?php namespace app\modules\anecdote\components\crons;

use app\commands\modules\cron_manager\models\AbstractTask;
use app\components\telegram\api\TelegramAPI;
use app\modules\anecdote\models\Entity\Anecdote;
use Yii;

/**
 * Class AnecdoteParseCronTask
 * @package app\components\crons
 */
class AnecdotePostCronTask extends AbstractTask
{
    // фоновые изображения
    private static $image_template = [
        'texture.jpg',
        'black.png',
        'gray.jpg',
        'bluu.jpg',
        'red.jpg',
        'asd.jpg',
        'black2.jpg',
    ];

    //таймер
    public function getSchedulerTime(): string
    {
        return "*/10 * * * *";
    }

    //отправка поста
    public function execute(): void
    {
        $text = $this->getText();
        if (mb_strlen($text) > 240) {
            (new TelegramAPI())->sendMessage(env('TELEGRAM_ANECDOTE_CHAT'), $text);
        } else {
            $photo = $this->gePhoto($text);
            (new TelegramAPI())->sendPhoto(env('TELEGRAM_ANECDOTE_CHAT'), $photo);
        }
    }


    private function gePhoto($text)
    {
        array_map('unlink', glob(Yii::getAlias("@app/runtime/photo_post/*")));

        $row_count = substr_count($text, "\n");

        $im_width  = 700;
        $im_height = round(500 + $row_count * (500 / 100 * 7));

        $im_path = Yii::getAlias('@app/web/images/'.self::$image_template[rand(0,count(self::$image_template)-1)]);
        if(preg_match('/^.*(.png)$/',$im_path)) {
            $im0 = imagecreatefrompng($im_path);
        }else{
            $im0 = imagecreatefromjpeg($im_path);
        }
        $im = imagecrop($im0, ['x' => 0, 'y' => 0, 'width' => $im_width, 'height' => $im_height]);

        $white = imagecolorallocate($im, 255, 255, 255);
        $blue = imagecolorallocate($im, 75, 215, 195);

        // Путь к ttf файлу шрифта
        $font_file = Yii::getAlias('@app/web/fonts/AlegreyaSC-Bold.ttf');


        $size = 28;
        $x            = round($im_width * 0.15 - $row_count * ($im_width * 0.15 / 100 * 2.7));
        $y            = round($im_height * 0.4 - $row_count * ($im_height * 0.4 / 100 * 7));

        // Рисуем текст
        imagefttext($im, $size, 0, $x, $y, $white, $font_file, $text);
        imagefttext($im, 22, 0, $x/3, $im_height-20, $blue, $font_file, 'СмеXлыст@smehlist');

        if (!is_dir(Yii::getAlias('@app/runtime/photo_post'))) {
            mkdir(Yii::getAlias('@app/runtime/photo_post'), 0700);
        }
        $path = Yii::getAlias('@app/runtime/photo_post/' . time() . '.png');

        imagepng($im, $path);
        imagedestroy($im);

        return $path;
    }

    private function getText()
    {
        $model = Anecdote::find()
            ->where(
                [
                    'viewed' => false,
                ]
            )
            ->one()
        ;
        if (empty($model)) {
            $sql = 'UPDATE anecdote SET viewed=false;';
            \Yii::$app->db->createCommand($sql)->execute();
            $model = Anecdote::find()->one();
        }
        $model->viewed = true;
        $model->save();

        return $this->textFormater($model->text);
    }

    /**
     * рабзивает текст на строки
     * @param string $str
     * @return string
     */
    private function textFormater(string $str):string
    {
        $str_length = 30;

        $strs = explode('—', $str);
        $text = '';
        foreach ($strs as $str) {
            if (empty($str)) {
                continue;
            }
            if (count($strs) > 1) {
                $str = '— ' . trim($str);
            }

            while (mb_strlen($str) >= $str_length) {
                $s_pos = mb_strripos(mb_substr($str, 0, $str_length), ' ');
                $text  .= trim(mb_substr($str, 0, $s_pos)) . "\n";
                $str   = mb_substr($str, $s_pos);
            }

            $text .= $str . "\n";
        }
        $text = trim($text);

        return $text;
    }
}