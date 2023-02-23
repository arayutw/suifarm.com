<?php
class Content
{
    static public function getPath(int $id)
    {
        $parent = $id;

        $rows = [];

        while (true) {
            $row = RDS::fetch("SELECT `parent`, `name` FROM `content` WHERE `id`=? AND `status`=? LIMIT 1;", [
                $parent,
                1,
            ]);

            if ($row) {
                array_unshift($rows, $row);

                $parent = $row["parent"];

                if (!$parent) break;
            } else {
                break;
            }
        }

        return "/" . implode("/", array_column($rows, "name"));
    }

    static public function getId(string $path)
    {
        $parent = null;
        $tokens = explode("/", substr($path, 1));

        $rows = [];

        foreach ($tokens as $token) {
            $row = RDS::fetch("SELECT `id` FROM `content` WHERE `parent`" . ($parent ? "={$parent}" : " IS NULL") . " AND `name`=? AND `status`=? LIMIT 1;", [
                $token,
                1,
            ]);

            if ($row) {
                $parent = $row["id"];
                $rows[] = $row;
            } else {
                break;
            }
        }

        return $parent;
    }
}
