<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

use Laravel\Lumen\Routing\Controller as BaseController;

use DB;

class Jams extends BaseController
{
    /**
     * Get a jam by ID
     *
     * @param null $id
     * @return mixed
     */
    public function getJam($id = null)
    {
        $query = "SELECT * FROM jams";

        if (!is_null($id)) {
            $query .= " WHERE `id` = {$id}";
        }
        $query .= " ORDER BY timestamp DESC";
        return app('db')->select($query);
    }

    /**
     * Create a new jam by jammer ID and a Youtube ID
     *
     * @param $jammer_id
     * @param $youtube_id
     */
    public function createJam($jammer_id, $youtube_id)
    {
        // Get normalized jam data
        return $this->_decodeJamByYoutubeId($youtube_id, function ($e) use (&$jammer_id) {
            $result = DB::table('jams')
                ->insert([
                        'song' => $e['song'],
                        'artist' => $e['artist'],
                        'link' => $e['link'],
                        'embed' => $e['embed'],
                        'jammer_id' => $jammer_id,
                        'timestamp' => date('Y-m-d')
                    ]
                );

            if ($result) {
                // $this->_postToSlack($e);
                echo json_encode($e);
            }
            exit;
        });
    }

    /**
     * Update a jam by ID with given data
     *
     * @param         $id
     * @param Request $body
     * @return mixed
     */
    public function updateJam($id, Request $body)
    {
        $update = array();

        foreach (array('song', 'artist', 'link', 'jammer_id', 'timestamp') as $input) {
            if ($body->input($input)) {
                $update[$input] = $body->input($input);
            }
        }

        return DB::table('jams')
            ->where('id', $id)
            ->update($update);
    }

    /**
     * Delete a jam by ID
     *
     * @param $id
     * @return mixed
     */
    public function deleteJam($id)
    {
        return app('db')->update("DELETE FROM jams WHERE id = '{$id}'");
    }

    /**
     * Return a random jam
     *
     * @return mixed
     */
    public function wildcardJam()
    {
        return app('db')->select("SELECT * FROM jams ORDER BY RAND() LIMIT 1");
    }

    /**
     * Return a jam from a specified date, or one year ago
     *
     * @param null $date
     * @return mixed
     */
    public function historicJam($date = null)
    {
        // If a date is provided, select by that date
        if (is_null($date)) {
            $date = date('Y-m-d', strtotime('-1 year'));
        }
        return app('db')->select("SELECT * FROM jams WHERE timestamp = '{$date}' LIMIT 1");
    }

    /**
     * Decode video data from a Youtube ID
     *
     * @param $id
     * @param $callback
     */
    private function _decodeJamByYoutubeId($id, $callback)
    {
        $content = file_get_contents("http://youtube.com/get_video_info?video_id={$id}");
        parse_str($content, $ytarr);
        $title = explode('-', $ytarr['title']);

        // Create the array of video variables to return
        $result['artist'] = trim($title[0]);
        $result['song'] = trim($title[1]);
        $result['thumbnail'] = $ytarr['thumbnail_url'];
        $result['link'] = "https://www.youtube.com/watch?v={$id}";
        $result['embed'] = "https://www.youtube.com/embed/{$id}?rel=0&amp;controls=0&amp;showinfo=0";

        $callback($result);
    }

    private function _postToSlack($data)
    {
        $greeting = array(
            "Holy macaroni, Batman!  A new jam is attacking Gotham!",
            "New jam uploaded!  Prepare for audio overload.",
            "Are you ready to jam out?  You'd better be, it's jam time!",
            "Hold on to your socks, this jam goes hotter than the kelvin scale.",
            "3... 2... 1... JAM LAUNCHED!",
            "Jam alert!  Jam alert!"
        );

        $key = array_rand($greeting);

        // Build our message
        $message = <<<EOD
{$greeting[$key]}  {$data['song']} by {$data['artist']} is currently playing as the Jam of the Day.
EOD;

        // Build the post fields
        $postfields = array(
            'username' => 'JamBot',
            'text' => $message,
            'attachments' => array(
                array(
                    'fallback' => "{$data['song']} - {$data['artist']}",
                    'color' => '#df42f4',
                    "title" => "{$data['song']} - {$data['artist']}",
                    "title_link" => 'http://jam.s2.webgrain.net',
                    'image_url' => $data['thumbnail']
                )
            )
        );

        $postfields = json_encode($postfields);

        // Initialize CURL request
        $curl = curl_init();

        curl_setopt_array($curl, array(
            CURLOPT_URL => "https://hooks.slack.com/services/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",
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => "",
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => "POST",
            CURLOPT_POSTFIELDS => $postfields,//"{\"text\":\"{$message}\"}",
            CURLOPT_HTTPHEADER => array(
                "Cache-Control: no-cache",
                "Postman-Token: ea7f4641-3275-46ec-8232-c8473af42d8b"
            ),
        ));

        $response = curl_exec($curl);
        $err = curl_error($curl);

        curl_close($curl);

        if ($err) {
            echo "cURL Error #:" . $err;
        } else {
            echo $response;
        }
    }
}
