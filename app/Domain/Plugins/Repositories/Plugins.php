<?php

namespace Leantime\Domain\Plugins\Repositories {

    use Leantime\Core\Db as DbCore;
    use PDO;

    class Plugins
    {
        private DbCore $db;

        /**
         * __construct - get database connection
         *
         * @access public
         */
        public function __construct(DbCore $db)
        {
            $this->db = $db;
        }

        public function getAllPlugins($enabledOnly = true)
        {

            $query = "SELECT
                    id,
                  name,
                  enabled,
                  description,
                  version,
                  installdate,
                  foldername,
                  homepage,
                  authors



                FROM zp_plugins";

            if ($enabledOnly) {
                $query .= " WHERE enabled = true";
            }

            $query .= " GROUP BY name ";

            $stmn = $this->db->database->prepare($query);

            $stmn->execute();
            $stmn->setFetchMode(PDO::FETCH_CLASS, "Leantime\Domain\Plugins\Models\Plugins");
            $allPlugins = $stmn->fetchAll();

            foreach ($allPlugins as &$row) { // Use reference so we can modify in place
                $row->authors = json_decode($row->authors);
            }

            return $allPlugins;
        }

        public function getPlugin(int $id): \Leantime\Domain\Plugins\Models\Plugins|false
        {

            $query = "SELECT
                    id,
                  name,
                  enabled,
                  description,
                  version,
                  installdate,
                  foldername,
                  homepage,
                  authors

                FROM zp_plugins";


            $stmn = $this->db->database->prepare($query);

            $stmn->execute();
            $stmn->setFetchMode(PDO::FETCH_CLASS, "Leantime\Domain\Plugins\Models\Plugins");
            $plugin = $stmn->fetch();

            return $plugin;
        }

        public function addPlugin(\Leantime\Domain\Plugins\Models\Plugins $plugin)
        {

            $sql = "INSERT INTO zp_plugins (
                    name,
                   enabled,
                   description,
                   version,
                   installdate,
                   foldername,
                   homepage,
                   authors
            ) VALUES (
                :name,
                :enabled,
                :description,
                :version,
                :installdate,
                :foldername,
                :homepage,
                :authors
            )";

            $stmn = $this->db->database->prepare($sql);
            $stmn->bindValue(':name', $plugin->name, PDO::PARAM_STR);
            $stmn->bindValue(':enabled', $plugin->enabled, PDO::PARAM_STR);
            $stmn->bindValue(':description', $plugin->description, PDO::PARAM_STR);
            $stmn->bindValue(':version', $plugin->version, PDO::PARAM_STR);
            $stmn->bindValue(':installdate', $plugin->installdate, PDO::PARAM_STR);
            $stmn->bindValue(':foldername', $plugin->foldername, PDO::PARAM_STR);
            $stmn->bindValue(':homepage', $plugin->homepage, PDO::PARAM_STR);
            $stmn->bindValue(':authors', $plugin->authors, PDO::PARAM_STR);

            $stmn->execute();

            $id = $this->db->database->lastInsertId();
            $stmn->closeCursor();

            return $id;
        }

        public function enablePlugin(int $id)
        {

            $sql = "UPDATE zp_plugins
                   SET enabled = 1
                WHERE id = :id
            ";

            $stmn = $this->db->database->prepare($sql);
            $stmn->bindValue(':id', $id, PDO::PARAM_INT);

            $result = $stmn->execute();

            $stmn->closeCursor();

            return $result;
        }

        public function disablePlugin(int $id)
        {

            $sql = "UPDATE zp_plugins
                   SET enabled = 0
                WHERE id = :id
            ";

            $stmn = $this->db->database->prepare($sql);
            $stmn->bindValue(':id', $id, PDO::PARAM_INT);

            $result = $stmn->execute();

            $stmn->closeCursor();

            return $result;
        }

        public function removePlugin(int $id)
        {

            $sql = "DELETE FROM zp_plugins
                WHERE id = :id LIMIT 1
            ";

            $stmn = $this->db->database->prepare($sql);
            $stmn->bindValue(':id', $id, PDO::PARAM_INT);

            $result = $stmn->execute();

            $stmn->closeCursor();

            return $result;
        }
    }

}
