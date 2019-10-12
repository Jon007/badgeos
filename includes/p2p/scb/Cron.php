<?php

if ( ! defined( 'ABSPATH' ) )
	exit; // Exit if accessed directly

/**
 * wp-cron job container
 */
class scbCron {
	protected $schedule;
	protected $interval;
	protected $time;

	protected $hook;
	protected $callback_args = array();

	/**
	 * Create a new cron job
	 *
	 * @param string|bool $file Reference to main plugin file
	 * @param array       $args List of args:
	 *                          string $action OR callback $callback
	 *                          string $schedule OR number $interval
	 *                          array $callback_args (optional)
	 */
	function __construct( $file = false, $args ) {

		// Set time & schedule
		if ( isset( $args['time'] ) )
			$this->time = $args['time'];

		if ( isset( $args['interval'] ) ) {
			$this->schedule = $args['interval'] . 'secs';
			$this->interval = $args['interval'];
		} elseif ( isset( $args['schedule'] ) ) {
			$this->schedule = $args['schedule'];
		}

		// Set hook
		if ( isset( $args['action'] ) ) {
			$this->hook = $args['action'];
		} elseif ( isset( $args['callback'] ) ) {
			$this->hook = self::_callback_to_string( $args['callback'] );
			add_action( $this->hook, $args['callback'] );
		} elseif ( method_exists( $this, 'callback' ) ) {
			$this->hook = self::_callback_to_string( array( $this, 'callback' ) );
			add_action( $this->hook, $args['callback'] );
		} else {
			trigger_error( '$action OR $callback not set', E_USER_WARNING );
		}

		if ( isset( $args['callback_args'] ) )
			$this->callback_args = (array) $args['callback_args'];

		if ( $file && $this->schedule ) {
			scbUtil::add_activation_hook( $file, array( $this, 'reset' ) );
			register_deactivation_hook( $file, array( $this, 'unschedule' ) );
		}

		add_filter( 'cron_schedules', array( $this, '_add_timing' ) );
	}

	/**
	 * Change the interval of the cron job
	 *
	 * @param array $args List of args:
	 *                    string $schedule OR number $interval
	 *                    timestamp $time ( optional )
	 */
	function reschedule( $args ) {

		if ( $args['schedule'] && $this->schedule != $args['schedule'] ) {
			$this->schedule = $args['schedule'];
		} elseif ( $args['interval'] && $this->interval != $args['interval'] ) {
			$this->schedule = $args['interval'] . 'secs';
			$this->interval = $args['interval'];
		}

		$this->time = $args['time'];

		$this->reset();
	}

	/**
	 * Reset the schedule
	 */
	function reset() {
		$this->unschedule();
		$this->schedule();
	}

	/**
	 * Clear the cron job
	 */
	function unschedule() {
#		wp_clear_scheduled_hook( $this->hook, $this->callback_args );
		self::really_clear_scheduled_hook( $this->hook );
	}

	/**
	 * Execute the job now
	 * @param array $args List of arguments to pass to the callback
	 */
	function do_now( $args = null ) {
		if ( is_null( $args ) )
			$args = $this->callback_args;

		do_action_ref_array( $this->hook, $args );
	}

	/**
	 * Execute the job with a given delay
	 * @param int   $delay in seconds
	 * @param array $args  List of arguments to pass to the callback
	 */
	function do_once( $delay = 0, $args = null ) {
		if ( is_null( $args ) )
			$args = $this->callback_args;

		wp_clear_scheduled_hook( $this->hook, $args );
		wp_schedule_single_event( time() + $delay, $this->hook, $args );
	}


//_____INTERNAL METHODS_____

	/**
	 * @param array $schedules
	 *
	 * @return array
	 */
	function _add_timing( $schedules ) {
		if ( isset( $schedules[$this->schedule] ) )
			return $schedules;

		$schedules[$this->schedule] = array(
			'interval' => $this->interval,
			'display'  => $this->interval . ' seconds',
		);

		return $schedules;
	}

	protected function schedule() {
		if ( ! $this->time )
			$this->time = time();

		wp_schedule_event( $this->time, $this->schedule, $this->hook, $this->callback_args );
	}

	/**
	 * @param string $name
	 */
	protected static function really_clear_scheduled_hook( $name ) {
		$crons = _get_cron_array();

		foreach ( $crons as $timestamp => $hooks ) {
			foreach ( $hooks as $hook => $args )
				if ( $hook == $name )
					unset( $crons[$timestamp][$hook] );

			if ( empty( $crons[$timestamp] ) )
				unset( $crons[$timestamp] );
		}

		_set_cron_array( $crons );
	}

	/**
	 * @param callback $callback
	 *
	 * @return string
	 */
	protected static function _callback_to_string( $callback ) {
		if ( ! is_array( $callback ) )
			$str = $callback;
		elseif ( ! is_string( $callback[0] ) )
			$str = get_class( $callback[0] ) . '_' . $callback[1];
		else
			$str = $callback[0] . '::' . $callback[1];

		$str .= '_hook';

		return $str;
	}
}

