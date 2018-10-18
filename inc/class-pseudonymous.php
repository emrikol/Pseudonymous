<?php
/**
 * Main file for Pseudonymous class.
 *
 * @package WordPress
 *
 * phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.VIP.DirectDatabaseQuery.DirectQuery,WordPress.VIP.DirectDatabaseQuery.NoCaching,WordPress.VIP.RestrictedVariables.user_meta__wpdb__users
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The primary class for Pseudonymous.
 */
class Pseudonymous {
	/**
	 * The unique instance of the plugin.
	 *
	 * @var Pseudonymous
	 */
	private static $instance;

	/**
	 * Gets an instance of our plugin.
	 *
	 * @return Pseudonymous
	 */
	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Initializes hooks for admin screen.
	 */
	public function init_hooks() {
		if ( ! is_admin() ) {
			add_filter( 'author_email', array( $this, 'anonymize_author_email' ), PHP_INT_MIN, 2 );
			add_filter( 'get_the_author_user_email', array( $this, 'anonymize_get_the_author_user_email' ), PHP_INT_MIN, 3 ); // Filter for get_the_author_meta.
			add_filter( 'author_link', array( $this, 'anonymize_author_link' ), PHP_INT_MIN, 3 );
			add_filter( 'get_comment_author_url', array( $this, 'anonymize_get_comment_author_url' ), PHP_INT_MIN, 3 );
			add_filter( 'get_the_author_user_url', array( $this, 'anonymize_get_the_author_user_url' ), PHP_INT_MIN, 3 ); // Filter for get_the_author_meta.
			add_filter( 'get_comment_author', array( $this, 'anonymize_get_comment_author' ), PHP_INT_MIN, 3 );
			add_filter( 'get_comment', array( $this, 'anonymize_get_comment' ), PHP_INT_MIN, 3 );
			add_filter( 'the_author', array( $this, 'anonymize_the_author' ), PHP_INT_MIN, 1 );
			add_filter( 'comment_class', array( $this, 'anonymize_comment_class' ), PHP_INT_MIN, 5 );
			add_filter( 'get_the_author_user_login', array( $this, 'anonymize_get_the_author_user_login' ), PHP_INT_MIN, 3 ); // Filter for get_the_author_meta.
			add_filter( 'get_the_author_user_nicename', array( $this, 'anonymize_get_the_author_user_nicename' ), PHP_INT_MIN, 3 ); // Filter for get_the_author_meta.
			add_action( 'pre_user_query', array( $this, 'anonymize_pre_user_query' ), PHP_INT_MIN, 1 );
			add_filter( 'query', array( $this, 'anonymize_wp_user_get_data_by' ), PHP_INT_MIN, 1 );
			add_filter( 'pre_get_posts', array( $this, 'anonymize_author_permalink' ), PHP_INT_MIN, 1 );
			add_filter( 'get_avatar_url', array( $this, 'anonymize_get_avatar_url' ), PHP_INT_MIN, 3 );
			add_filter( 'rest_prepare_user', array( $this, 'anonymize_rest_prepare_user' ), PHP_INT_MIN, 3 );

			add_filter( 'wp_title', array( $this, 'itg_anonymize_htmltitle' ) );
		} else {
			$gw_admin = Pseudonymous_Admin::get_instance();
			$gw_admin->init_hooks();
		}

		$user = WP_User::get_data_by( 'ID', 2 );
		update_user_caches( $user );
	}

	/**
	 * Filters user data returned from the REST API.
	 *
	 * @param WP_REST_Response $response The response object.
	 * @param object           $user     User object used to create response.
	 * @param WP_REST_Request  $request  Request object.
	 */
	public function anonymize_rest_prepare_user( $response, $user, $request ) {
		if ( isset( $response->data['name'] ) ) {
			$pseudonymous_user_nicename = get_user_meta( $response->data['id'], 'pseudonymous_user_nicename', true );
			if ( $pseudonymous_user_nicename ) {
				$response->data['name'] = $pseudonymous_user_nicename;
			}
		}

		if ( isset( $response->data['slug'] ) ) {
			$pseudonymous_user_login = get_user_meta( $response->data['id'], 'pseudonymous_user_login', true );
			if ( $pseudonymous_user_login ) {
				$response->data['slug'] = $pseudonymous_user_login;
			}
		}

		if ( isset( $response->data['avatar_urls'] ) ) {
			$pseudonymous_user_email = get_user_meta( $response->data['id'], 'pseudonymous_user_email', true );
			if ( $pseudonymous_user_email ) {
				$response->data['avatar_urls'] = rest_get_avatar_urls( $pseudonymous_user_email );
			}
		}

		return $response;
	}

	/**
	 * Filters the avatar URL.
	 *
	 * @param string $url         The URL of the avatar.
	 * @param mixed  $id_or_email The Gravatar to retrieve. Accepts a user_id, gravatar md5 hash, user email, WP_User object, WP_Post object, or WP_Comment object.
	 * @param array  $args        Arguments passed to get_avatar_data(), after processing.
	 */
	public function anonymize_get_avatar_url( $url, $id_or_email, $args ) {
		remove_filter( 'get_avatar_url', array( $this, 'anonymize_get_avatar_url' ), PHP_INT_MIN, 3 );

		if ( $id_or_email instanceof WP_Comment ) {
			$user = get_user_by( 'email', $id_or_email->comment_author_email );
			if ( $user ) {
				$pseudonymous_user_email = get_user_meta( $user->ID, 'pseudonymous_user_email', true );
				if ( ! empty( $pseudonymous_user_email ) ) {
					$url = get_avatar_url( $pseudonymous_user_email );
				}
			} else {
				$url = get_avatar_url( $id_or_email->comment_author_email );
			}
		} elseif ( $id_or_email instanceof WP_User ) {
			$pseudonymous_user_email = get_user_meta( $id_or_email->ID, 'pseudonymous_user_email', true );
			if ( ! empty( $pseudonymous_user_email ) ) {
				$url = get_avatar_url( $pseudonymous_user_email );
			}
		} elseif ( $id_or_email instanceof WP_Post ) {
			$user = get_user_by( 'id', (int) $id_or_email->post_author );
			if ( $user ) {
				$pseudonymous_user_email = get_user_meta( $user->ID, 'pseudonymous_user_email', true );
				if ( ! empty( $pseudonymous_user_email ) ) {
					$url = get_avatar_url( $pseudonymous_user_email );
				}
			}
		}

		add_filter( 'get_avatar_url', array( $this, 'anonymize_get_avatar_url' ), PHP_INT_MIN, 3 );
		return $url;
	}

	/**
	 * Filters the returned CSS classes for the current comment.
	 *
	 * @param array       $classes    An array of comment classes.
	 * @param string      $class      A comma-separated list of additional classes added to the list.
	 * @param int         $comment_id The comment id.
	 * @param WP_Comment  $comment    The comment object.
	 * @param int|WP_Post $post_id    The post ID or WP_Post object.
	 */
	public function anonymize_comment_class( $classes, $class, $comment_id, $comment, $post_id ) {
		if ( isset( $comment->comment_author_email ) ) {
			$user = get_user_by( 'email', $comment->comment_author_email );
			if ( $user ) {
				$user_login              = $user->user_login;
				$pseudonymous_user_login = get_user_meta( $user->ID, 'pseudonymous_user_login', true );

				if ( $pseudonymous_user_login ) {
					$classes = array_map(
						function( $class ) use ( $user_login, $pseudonymous_user_login ) {
							return str_replace( $user_login, $pseudonymous_user_login, $class );
						},
						$classes
					);
				}
			}
		}

		return $classes;
	}

	/**
	 * Generates a URL to an author's posts using their anonimized name.
	 *
	 * @param string $link            The URL to the author's page.
	 * @param int    $author_id       The author's id.
	 * @param string $author_nicename The author's nice name.
	 *
	 * @return string The filtered author link.
	 */
	public function anonymize_author_link( $link = '', $author_id = 0, $author_nicename = '' ) {
		$user = WP_User::get_data_by( 'ID', $author_id );

		if ( $user ) {
			$user_login              = $user->user_login;
			$pseudonymous_user_login = get_user_meta( $author_id, 'pseudonymous_user_login', true );

			if ( $user_login && $pseudonymous_user_login ) {
				$link = str_replace( $user_login, $pseudonymous_user_login, $link );
			}
		}

		return $link;
	}

	/**
	 * Function to anonymize a user e-mail address
	 *
	 * @param string $author_email The author's email address.
	 * @param int    $comment_id   The author's id.
	 */
	public function anonymize_author_email( $author_email, $comment_id ) {
		$user = WP_User::get_data_by( 'email', $author_email );

		if ( $user ) {
			$pseudonymous_user_email = get_user_meta( $user->ID, 'pseudonymous_user_email', true );

			if ( $pseudonymous_user_email ) {
				return $pseudonymous_user_email;
			}
		}

		return $author_email;
	}

	/**
	 * Filters the value of the requested user metadata.
	 *
	 * @param string   $value            The value of the metadata.
	 * @param int      $user_id          The user ID for the value.
	 * @param int|bool $original_user_id The original user ID, as passed to the function.
	 */
	public function anonymize_get_the_author_user_email( $value = '', $user_id = 0, $original_user_id = 0 ) {
		return self::anonymize_author_email( $value, $user_id );
	}

	/**
	 * Filters the value of the requested user metadata.
	 *
	 * @param string   $value            The value of the metadata.
	 * @param int      $user_id          The user ID for the value.
	 * @param int|bool $original_user_id The original user ID, as passed to the function.
	 */
	public function anonymize_get_the_author_user_url( $value = '', $user_id = 0, $original_user_id = 0 ) {
		return self::anonymize_author_link( $value, $user_id, 0 );
	}

	/**
	 * Filters the value of the requested user metadata.
	 *
	 * @param string   $value            The value of the metadata.
	 * @param int      $user_id          The user ID for the value.
	 * @param int|bool $original_user_id The original user ID, as passed to the function.
	 */
	public function anonymize_get_the_author_user_login( $value = '', $user_id = 0, $original_user_id = 0 ) {
		$pseudonymous_user_login = get_user_meta( $user_id, 'pseudonymous_user_login', true );
		return ! empty( $pseudonymous_user_login ) ? $pseudonymous_user_login : $value;
	}

	/**
	 * Filters the value of the requested user metadata.
	 *
	 * @param string   $value            The value of the metadata.
	 * @param int      $user_id          The user ID for the value.
	 * @param int|bool $original_user_id The original user ID, as passed to the function.
	 */
	public function anonymize_get_the_author_user_nicename( $value = '', $user_id = 0, $original_user_id = 0 ) {
		$pseudonymous_user_nicename = get_user_meta( $user_id, 'pseudonymous_user_nicename', true );
		return ! empty( $pseudonymous_user_nicename ) ? $pseudonymous_user_nicename : $value;
	}

	/**
	 * Filters the comment author's URL.
	 *
	 * @param string     $url        The comment author's URL.
	 * @param int        $comment_id The comment ID.
	 * @param WP_Comment $comment    The comment object.
	 */
	public function anonymize_get_comment_author_url( $url, $comment_id, $comment ) {
		if ( isset( $comment->comment_author_email ) ) {
			$user = get_user_by( 'email', $comment->comment_author_email );
			if ( $user ) {
				$url = self::anonymize_author_link( $url, $user->ID );
			}
		}
		return $url;
	}

	/**
	 * Fires after a comment is retrieved.
	 *
	 * @param mixed $_comment Comment data.
	 */
	public function anonymize_get_comment( $_comment ) {
		if ( $_comment instanceof WP_Comment ) {
			$user = get_user_by( 'email', $_comment->comment_author_email );
			if ( $user ) {
				$pseudonymous_user_nicename = get_user_meta( $user->ID, 'pseudonymous_user_nicename', true );
				if ( ! empty( $pseudonymous_user_nicename ) ) {
					$_comment->comment_author = $pseudonymous_user_nicename;
				}

				$_comment->comment_author_email = self::anonymize_author_email( $_comment->comment_author_email, $user->ID );
			}
		}

		return $_comment;
	}

	/**
	 * Filters the returned comment author name.
	 *
	 * @param string     $author     The comment author's username.
	 * @param int        $comment_id The comment ID.
	 * @param WP_Comment $comment    The comment object.
	 */
	public function anonymize_get_comment_author( $author, $comment_id, $comment ) {
		if ( isset( $comment->comment_author_email ) ) {
			$user = get_user_by( 'email', $comment->comment_author_email );
			if ( $user ) {
				$pseudonymous_user_nicename = get_user_meta( $user->ID, 'pseudonymous_user_nicename', true );
				return ! empty( $pseudonymous_user_nicename ) ? $pseudonymous_user_nicename : $author;
			}
		}
		return $author;
	}

	/**
	 * Filters the display name of the current post's author.
	 *
	 * @param string $display_name The author's display name.
	 */
	public function anonymize_the_author( $display_name ) {
		global $authordata;

		if ( isset( $authordata->ID ) ) {
			$pseudonymous_user_nicename = get_user_meta( $authordata->ID, 'pseudonymous_user_nicename', true );
			return ! empty( $pseudonymous_user_nicename ) ? $pseudonymous_user_nicename : $display_name;
		}

		return $display_name;
	}

	/**
	 * Fires after the WP_User_Query has been parsed, and before the query is executed.
	 *
	 * @param WP_User_Query $user_query The current WP_User_Query instance, passed by reference.
	 */
	public function anonymize_pre_user_query( $user_query ) {
		global $wpdb;

		$query_vars =& $user_query->query_vars;

		$user_query->request = "SELECT $user_query->query_fields $user_query->query_from $user_query->query_where $user_query->query_orderby $user_query->query_limit"; // phpcs:ignore WordPress.DB.DirectDatabaseQuery.NoCaching

		// Set up a fake SQL query that we'll return with our anonymized data.
		$fake_sql = [];

		// If 'all' fields are selected.
		// TODO: What if only one field is queried?
		if ( is_array( $query_vars['fields'] ) || 'all' === $query_vars['fields'] ) {
			$results = $wpdb->get_results( $user_query->request, ARRAY_A ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.NotPrepared
			foreach ( $results as $result ) {
				foreach ( $result as $column_name => $value ) {
					switch ( $column_name ) {
						case 'user_login':
							$value = self::anonymize_get_the_author_user_login( $value, (int) $result['ID'], 0 );
							break;
						case 'user_nicename':
							$value = self::anonymize_get_the_author_user_login( $value, (int) $result['ID'], 0 );
							break;
						case 'user_email':
							$value = self::anonymize_get_the_author_user_email( $value, (int) $result['ID'], 0 );
							break;
						case 'user_url':
							$value = self::anonymize_get_the_author_user_url( $value, (int) $result['ID'], 0 );
							break;
						case 'display_name':
							$value = self::anonymize_get_the_author_user_nicename( $value, (int) $result['ID'], 0 );
							break;
					}
					$value                  = esc_sql( $value );
					$column_name            = esc_sql( $column_name );
					$result[ $column_name ] = "$value' as '$column_name";
				}
				$fake_sql[] = "'" . join( "', '", $result ) . "'";
			}
		} else {
			$results = $wpdb->get_col( $user_query->request ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.NotPrepared
		}

		// Join the fake SQL parts and fix the string because this isn't a "full" query.
		$new_sql = join( ') UNION (SELECT ', array_filter( $fake_sql ) ) . ')';
		$count   = 1;
		$new_sql = str_replace( ') UNION', ' UNION', $new_sql, $count ); // Strip parentheses from first UNION.

		// Fix for single user sites.
		if ( false === strpos( $new_sql, ' UNION' ) ) { // Single user.
			$new_sql = substr( $new_sql, 0, -1 ); // Remove trailing ')'.
		}

		// Replace the query field and blank out the rest of the query bits that we don't need.
		$user_query->query_fields  = $new_sql;
		$user_query->query_from    = '';
		$user_query->query_where   = '';
		$user_query->query_orderby = '';
		$user_query->query_limit   = '';
	}

	/**
	 * Fires after the WP_User_Query has been parsed, and before the query is executed.
	 *
	 * Terrible hack for WP_User::get_data_by(); :(
	 * TODO: Update function for all other fields in get_data_by(): user_nicename, user_email, user_login.
	 *
	 * @param WP_Query $query The current WP_Query instance, passed by reference.
	 */
	public function anonymize_wp_user_get_data_by( $query ) {
		global $wpdb;

		// We have no need of running this query on any queries we're going to run.  Queryception.
		remove_filter( 'query', array( $this, 'anonymize_wp_user_get_data_by' ), PHP_INT_MIN, 1 );

		if (
			false !== strpos( $query, "SELECT * FROM $wpdb->users WHERE ID = " ) ||
			false !== strpos( $query, "SELECT * FROM $wpdb->users WHERE user_email = " )
		) {
			$real_result = $wpdb->get_row( $query ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.NotPrepared

			foreach ( $real_result as $column_name => $value ) {
				switch ( $column_name ) {
					case 'user_login':
						$value = self::anonymize_get_the_author_user_login( $value, (int) $real_result->ID, 0 );
						break;
					case 'user_nicename':
						$value = self::anonymize_get_the_author_user_login( $value, (int) $real_result->ID, 0 );
						break;
					case 'user_email':
						$value = self::anonymize_get_the_author_user_email( $value, (int) $real_result->ID, 0 );
						break;
					case 'user_url':
						$value = self::anonymize_get_the_author_user_url( $value, (int) $real_result->ID, 0 );
						break;
					case 'display_name':
						$display_name = get_user_meta( $real_result->ID, 'pseudonymous_user_nicename', true );
						if ( $display_name ) {
							$value = $display_name;
						}
						break;
				}
				$result[ $column_name ] = "$value' as '$column_name";
			}
			$fake_sql = "SELECT '" . join( "', '", $result ) . "'";

			return $fake_sql;
		}

		// Add the filter back in.
		add_filter( 'query', array( $this, 'anonymize_wp_user_get_data_by' ), PHP_INT_MIN, 1 );

		return $query;
	}

	/**
	 * Takes incoming query variables and redirects author values to point at the
	 * the author's real name and not the anonymized name.
	 *
	 * @param WP_Query $query The WP_Query instance (passed by reference).
	 */
	public function anonymize_author_permalink( $query ) {
		global $wpdb;

		$users = get_users(
			array(
				'meta_key' => 'pseudonymous_user_login', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key,WordPress.VIP.SlowDBQuery.slow_db_query_meta_key
			)
		);

		$author_ids = array();

		foreach ( (array) $users as $user ) {
			$author_ids[] = $user->ID;
		}

		if ( count( $author_ids ) > 0 ) {
			$author_ids = esc_sql( implode( ',', $author_ids ) );
			$authors    = $wpdb->get_results( "SELECT `ID`, `user_nicename` from $wpdb->users WHERE `ID` IN ( $author_ids ) " . 'ORDER BY `display_name`' ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.NotPrepared
		} else {
			$authors = array();
		}

		foreach ( (array) $authors as $author ) {
			$author                  = get_userdata( $author->ID );
			$pseudonymous_user_login = get_user_meta( $author->ID, 'pseudonymous_user_login', true );

			if ( isset( $query->query_vars['author_name'] ) && '' !== $query->query_vars['author_name'] ) {
				if ( $pseudonymous_user_login === $query->query_vars['author_name'] ) {
					$query->query_vars['author_name'] = $author->user_login;
				}
				if ( $pseudonymous_user_login === $query->query['author_name'] ) {
					$query->query['author_name'] = $author->user_login;
				}
			}
		}

		return $query;
	}

	/**
	 * Rewrites the HTML page's title to avoid displaying a user's name
	 *
	 * @param string $in The title to be filtered.
	 */
	public function itg_anonymize_htmltitle( $in ) {
		$auth = get_query_var( 'author_name' );
		if ( ( $auth === $in || ' &raquo; ' . $auth === $in ) && $in ) {
			$in = str_replace( ' &raquo; ', '', $in );
			$in = ' &raquo; ' . itg_generateName( $in );
		}
		return $in;
	}

}
