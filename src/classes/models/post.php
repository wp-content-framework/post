<?php
/**
 * WP_Framework_Post Classes Models Post
 *
 * @author Technote
 * @copyright Technote All Rights Reserved
 * @license http://www.opensource.org/licenses/gpl-2.0.php GNU General Public License, version 2
 * @link https://technote.space
 */

namespace WP_Framework_Post\Classes\Models;

use WP_Framework_Common\Traits\Uninstall;
use WP_Framework_Core\Traits\Hook;
use WP_Framework_Core\Traits\Singleton;
use WP_Framework_Post\Traits\Package;

if ( ! defined( 'WP_CONTENT_FRAMEWORK' ) ) {
	exit;
}

/**
 * Class Post
 * @package WP_Framework_Post\Classes\Models
 */
class Post implements \WP_Framework_Core\Interfaces\Singleton, \WP_Framework_Core\Interfaces\Hook, \WP_Framework_Common\Interfaces\Uninstall {

	use Singleton, Hook, Uninstall, Package;

	/**
	 * @return string
	 */
	private function get_post_prefix() {
		return $this->get_slug( 'post_prefix', '_post' ) . '-';
	}

	/**
	 * @param string $key
	 *
	 * @return string
	 */
	public function get_meta_key( $key ) {
		return $this->get_post_prefix() . $key;
	}

	/**
	 * @param bool $check_query
	 *
	 * @return int
	 */
	public function get_post_id( $check_query = false ) {
		global $post, $wp_query;
		if ( ! isset( $post ) ) {
			if ( $check_query && isset( $wp_query, $wp_query->query_vars['p'] ) ) {
				$post_id = $wp_query->query_vars['p'];
			} else {
				$post_id = 0;
			}
		} else {
			$post_id = $post->ID;
		}

		return $post_id;
	}

	/**
	 * @param string $key
	 * @param int|null $post_id
	 * @param bool $single
	 * @param mixed $default
	 *
	 * @return mixed
	 */
	public function get( $key, $post_id = null, $single = true, $default = '' ) {
		if ( ! isset( $post_id ) ) {
			$post_id = $this->get_post_id( true );
		}
		if ( $post_id <= 0 ) {
			return $this->apply_filters( 'get_post_meta', $default, $key, $post_id, $single, $default, $this->get_post_prefix() );
		}

		return $this->apply_filters( 'get_post_meta', get_post_meta( $post_id, $this->get_meta_key( $key ), $single ), $key, $post_id, $single, $default, $this->get_post_prefix() );
	}

	/**
	 * @param int $post_id
	 * @param string $key
	 * @param mixed $value
	 * @param bool $add
	 * @param bool $unique
	 *
	 * @return bool|int
	 */
	public function set( $post_id, $key, $value, $add = false, $unique = false ) {
		if ( $post_id <= 0 ) {
			return false;
		}

		if ( ! $add ) {
			return update_post_meta( $post_id, $this->get_meta_key( $key ), $value );
		}

		if ( $unique ) {
			$values = $this->get( $key, $post_id, false, [] );
			if ( in_array( $value, $values, true ) ) {
				return false;
			}
		}

		return add_post_meta( $post_id, $this->get_meta_key( $key ), $value );
	}

	/**
	 * @param int $post_id
	 * @param string $key
	 * @param mixed $meta_value
	 *
	 * @return bool
	 */
	public function delete( $post_id, $key, $meta_value = '' ) {
		if ( $post_id <= 0 ) {
			return false;
		}

		return delete_post_meta( $post_id, $this->get_meta_key( $key ), $meta_value );
	}

	/**
	 * @param string $key
	 * @param string $value
	 */
	public function set_all( $key, $value ) {
		$query = $this->wpdb()->prepare( "UPDATE {$this->get_wp_table('postmeta')} SET meta_value = %s WHERE meta_key LIKE %s", $value, $this->get_meta_key( $key ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$this->wpdb()->query( $query ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
	}

	/**
	 * @param string $key
	 */
	public function delete_all( $key ) {
		$query = $this->wpdb()->prepare( "DELETE FROM {$this->get_wp_table('postmeta')} WHERE meta_key LIKE %s", $this->get_meta_key( $key ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$this->wpdb()->query( $query ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
	}

	/**
	 * @param string $key
	 * @param string $value
	 *
	 * @return bool
	 */
	public function delete_matched( $key, $value ) {
		$post_ids = $this->find( $key, $value );
		if ( empty( $post_ids ) ) {
			return true;
		}
		foreach ( $post_ids as $post_id ) {
			$this->delete( $key, $post_id );
		}

		return true;
	}

	/**
	 * @param string $key
	 * @param string $value
	 *
	 * @return array
	 */
	public function find( $key, $value ) {
		$query   = <<< SQL
			SELECT * FROM {$this->get_wp_table( 'postmeta' )}
			WHERE meta_key LIKE %s
			AND   meta_value LIKE %s
SQL;
		$results = $this->wpdb()->get_results( $this->wpdb()->prepare( $query, $this->get_meta_key( $key ), $value ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared

		return $this->apply_filters( 'find_post_meta', $this->app->array->pluck( $results, 'post_id' ), $key, $value );
	}

	/**
	 * @param string $key
	 * @param string $value
	 *
	 * @return false|int
	 */
	public function first( $key, $value ) {
		$post_ids = $this->find( $key, $value );
		if ( empty( $post_ids ) ) {
			return false;
		}

		return reset( $post_ids );
	}

	/**
	 * @param string $key
	 *
	 * @return array
	 */
	public function get_meta_post_ids( $key ) {
		$query   = <<< SQL
		SELECT post_id FROM {$this->get_wp_table( 'postmeta' )}
		WHERE meta_key LIKE %s
SQL;
		$results = $this->wpdb()->get_results( $this->wpdb()->prepare( $query, $this->get_meta_key( $key ) ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared

		return $this->apply_filters( 'get_meta_post_ids', $this->app->array->pluck( $results, 'post_id' ), $key );
	}

	/**
	 * uninstall
	 */
	public function uninstall() {
		$query = $this->wpdb()->prepare( "DELETE FROM {$this->get_wp_table('postmeta')} WHERE meta_key LIKE %s", $this->get_post_prefix() . '%' ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$this->wpdb()->query( $query ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
	}

	/**
	 * @return int
	 */
	public function get_uninstall_priority() {
		return 100;
	}
}
