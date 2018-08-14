<?php

namespace App\Http\Controllers;

use Laravel\Lumen\Routing\Controller as BaseController;

use DB;

class Jammers extends BaseController
{
    /**
     * Return either a single or list of jammers
     *
     * @param null $id
     * @param bool $active
     *
     * @return mixed
     */
    public function getJammer($id = NULL, $active = TRUE)
    {
        $query = "SELECT * FROM jotd.jammers";

        if ( ! is_null($id)) {
            $query .= " WHERE `id` = {$id}";
        }
        if ($active) {
            $query .= ! is_null($id) ? " AND" : " WHERE";
            $query .= " `status` = '1'";
        }

        $query .= " ORDER BY `name` ASC";

        return app('db')->select($query);
    }

    /**
     * Returns all jammers regardless of id or status
     *
     * @return mixed
     */
    public function getJammers()
    {
        return $this->getJammer(NULL, FALSE);
    }

    /**
     * Create a new jammer by name
     *
     * @param $name
     *
     * @return mixed
     */
    public function createJammer($name)
    {
        $result = app('db')->update("INSERT INTO jammers (name, status) VALUES ('{$name}', '1')", [$name, 1]);

        if ($result) {
            return app('db')->select("SELECT LAST_INSERT_ID()");
        }
    }

    /**
     * Update a jammer's status by ID
     *
     * @param $id
     * @param $status
     *
     * @return mixed
     */
    public function updateJammer($id, $status)
    {
        return app('db')->update("UPDATE jammers SET status = '{$status}' WHERE id = '{$id}'");
    }

    /**
     * Delete a jammer by ID
     *
     * @param $id
     *
     * @return mixed
     */
    public function deleteJammer($id)
    {
        return app('db')->update("DELETE FROM jammers WHERE id = '{$id}'");
    }

    /**
     * Return a random jammer, excluding in-eligible jammer
     *
     * @param null $excludeId
     *
     * @return string
     */
    public function getRandomJammer($excludeId = NULL)
    {
        $exclude = $this->_excludeFromMonthlyJammerList();

        if ( ! is_null($excludeId)) {
            $exclude[] = $excludeId;
        }

        return $this->_random(
            $this->getJammer(),
            $exclude);
    }

    /**
     * Get jammers from either the current date, or a specified date.  If a
     * jammer isn't available for the current day, then select one, add them to
     * the DB, and serve them.
     *
     * @param null $date
     *
     * @return mixed
     */
    public function getJammerByDate($date = NULL)
    {
        // If a date is provided, select by that date
        if (is_null($date)) {
            $date = date('Y-m-d');
        }
        $jammer = app('db')->select("SELECT jammer_id FROM jotd.jammer_dates WHERE date = '{$date}'");

        // If no specific date is supplied, and the jammer is empty, then we must select a jammer and try again
        if (empty($jammer) && $date === date('Y-m-d')) {
            $newJammer = json_decode($this->getRandomJammer())->id;
            $result = \DB::table('jammer_dates')
                ->insert([
                             'jammer_id' => $newJammer,
                             'date'      => $date,
                         ]
                );
            if ($result) {
                $jammer = $this->getJammerByDate($date);
            }
        }

        return $jammer;
    }

    public function getHighScoreJammer()
    {
        $highscore = app('db')->select("
SELECT COUNT(jams.jammer_id) AS counts, jams.jammer_id
FROM jotd.jams
GROUP BY jams.jammer_id
ORDER BY counts DESC
LIMIT 1");

        $jammer = $this->getJammer($highscore[0]->jammer_id, FALSE);
        $jammer[0]->score = $highscore[0]->counts;
        return $jammer;
    }

    /**
     * Select a random jammer object from a list of jammer, while excluding any
     * given IDs
     *
     * @param       $jammers
     * @param array $excludeIds
     *
     * @return string
     */
    private function _random($jammers, $excludeIds = array())
    {
        // If id(s) have been excluded, then unset them from the jammers
        if ( ! empty($excludeIds)) {
            $jammers = $this->_exclude($jammers, $excludeIds);
        }

        return json_encode($jammers[array_rand($jammers)]);
    }

    /**
     * Provide a revised list of jammers, excluding a list of given IDs
     *
     * @param $jammers
     * @param $ids
     *
     * @return mixed
     */
    private function _exclude($jammers, $ids)
    {
        foreach ($jammers as $key => $jammer) {
            if (in_array($jammer->id, $ids)) {
                unset($jammers[$key]);
            }
        }

        return $jammers;
    }

    /**
     * Check all jammers in a given month and determine whether they are
     * eligible for a new jam
     *
     * @return array
     */
    private function _excludeFromMonthlyJammerList()
    {
        $thisMonth = (new \DateTime('first day of this month'))->format('Y-m-d');

        // Get all of the jammers dates from the current month
        $ids = app('db')->select("SELECT jammer_id FROM jotd.jammer_dates WHERE date >= '{$thisMonth}'");

        // Iterate over the list and count the number of instances of each jammer
        $listIds = array();
        foreach ($ids as $id) {
            $current_id = $id->jammer_id;
            $listIds[$current_id] = isset($listIds[$current_id]) ? ++$listIds[$current_id] : 1;
        }

        // Get the number of active jammers overall
        $count = count($this->getJammer());

        // Check that the number of jams per jammer does not exceed their monthly quota
        $exclude = array();
        foreach ($listIds as $id => $total) {
            if ($total >= (float) round(date('t') / $count)) {
                $exclude[] = $id;
            }
        }

        return $exclude;
    }

}
