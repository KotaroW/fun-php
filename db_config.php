<?php
/********************************************************************************
 * File Name:		db_config.php
 * Date:			15th February 2020
 * Written By:		KotaroW
 * Description:
 * 		Test and just for fun :-)
 *      We just wanted to manage DB credentials in one place not
 *      here and there.
********************************************************************************/


namespace DB_CONFIG {
    /*
    include-guard
    */
    if (!defined('SOMETHING')) {
        header('Location: URL for your 404 page');
    }

    /*
     * We do not want the class to be instantiated.
     * Maybe we could have acheived the same thing
     * by defining a private constructor.
     * This is a test anyway ...
     */
    abstract class DB_CONFIG {
        public const INDEX_HOST = 0;
        public const INDEX_USER = 1;
        public const INDEX_PWD  = 2;
        public const INDEX_DB   = 3;

        // index for the credentials
        // please make sure the constant values correspond
        // to the confidential array index
        public const PRIMARY_CREDENTIAL = 0;
        public const SECONDARY_CREDENTIAL = 1;

        // database confidentials
        // add more / less as required
        // array(host, user, password, database);
        private const DB_CREDENTIALS = array(
            [
                'host',
                'username',
                'password',
                'database1'
            ],
            [
                'host2',
                'username2',
                'password2',
                'testbase2'
            ],
        );

		/*
		 * credential getter
		 */
        public static function get_credential($credential_index) {
            return self::DB_CREDENTIALS[$credential_index];
        }

    }

}
// do not leave any empty lines after the closing tag.
?>
