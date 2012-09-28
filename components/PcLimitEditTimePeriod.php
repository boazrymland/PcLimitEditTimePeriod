<?php
/**
 * PcLimitEditTimePeriod.php
 *
 * @license:
 * Copyright (c) 2012, Boaz Rymland
 * All rights reserved.
 * Redistribution and use in source and binary forms, with or without modification, are permitted provided that the
 * following conditions are met:
 * - Redistributions of source code must retain the above copyright notice, this list of conditions and the following
 *      disclaimer.
 * - Redistributions in binary form must reproduce the above copyright notice, this list of conditions and the following
 *      disclaimer in the documentation and/or other materials provided with the distribution.
 * - The names of the contributors may not be used to endorse or promote products derived from this software without
 *      specific prior written permission.
 * THIS SOFTWARE IS PROVIDED BY THE COPYRIGHT HOLDERS AND CONTRIBUTORS "AS IS" AND ANY
 * EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT LIMITED TO, THE IMPLIED WARRANTIES OF
 * MERCHANTABILITY AND FITNESS FOR A PARTICULAR PURPOSE ARE DISCLAIMED. IN NO EVENT SHALL THE
 * COPYRIGHT HOLDER OR CONTRIBUTORS BE LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL, SPECIAL,
 * EXEMPLARY, OR CONSEQUENTIAL DAMAGES (INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF
 * SUBSTITUTE GOODS OR SERVICES; LOSS OF USE, DATA, OR PROFITS; OR BUSINESS INTERRUPTION)
 * HOWEVER CAUSED AND ON ANY THEORY OF LIABILITY, WHETHER IN CONTRACT, STRICT LIABILITY, OR
 * TORT (INCLUDING NEGLIGENCE OR OTHERWISE) ARISING IN ANY WAY OUT OF THE USE OF THIS SOFTWARE,
 * EVEN IF ADVISED OF THE POSSIBILITY OF SUCH DAMAGE.
 *
 */
class PcLimitEditTimePeriod extends CActiveRecordBehavior {
	/* @var int the timeout in which edit is allowed for this content, in MINUTES */
	public $timeout = 60;

	/* @var string the message to show when timeout has expired. Defaults to false meaning only generic message will be shown */
	public $message = false;

	/* @var string the attribute name that hold the 'created on' time.  */
	public $createdOnAttrName = "created_on";

	/* @var bool whether or not to use the logging system (Yii::log()) */
	public $useLogging = true;

	/* @var mixed the owner (=model object) 'created on' attribute value.
	 * Used in several locations in the code hence taken out to a convenience var here */
	private $_ownerCreatedOnValue;

	private $_defaultMessage = "Sorry - edit timeout has expired. Editing is not possible.";

	/**
	 * @return bool telling whether edit is allowed for this model or not.
	 */
	public function isEditAllowed() {
		// first, a little validation
		if (!$this->_validateCreateOnValue()) {
			// if validations failed we allow editing nevertheless (logging an error has been made from
			// the above validation methods).
			return true;
		}

		// parse the timestamp of the 'owner' created_on attribute, in unix timestamp (seconds) for easy comparison.
		if (!is_int($this->_ownerCreatedOnValue)) {
			$temp = new DateTime($this->_ownerCreatedOnValue);
			$timestamp = $temp->getTimestamp();
		}
		else {
			$timestamp = $this->_ownerCreatedOnValue;
		}

		// next, compare the value of the 'created on' attribute with the configured timeout
		$ddebug_now = time();
		$debug_timestamp_w_timeout = $timestamp + ($this->timeout * 60);
		if (($timestamp + ($this->timeout * 60)) >= time()) {
			// edit allowed
			return true;
		}
		// edit disallowed - time period has passed dude!
		return false;
	}

	/**
	 * This method does the check if the model edit time period has expired. If yes (expired), it will call 'render' in order to
	 * render an error message to the user.
	 *
	 * @return void
	 */
	public function disallowEditIfExpired() {
		// first, a little validation
		if (!$this->_validateCreateOnValue()) {
			// if either initial validations failed we allow editing nevertheless (logging an error has been made from
			// the above validation methods).
			return;
		}

		if (!$this->isEditAllowed()) {
			// check if we have a custom message or not:
			if ($this->message === false) {
				$this->message = $this->_defaultMessage;
			}
			Yii::app()->getController()->render("//general/edit_timeout_expired", array('message' => $this->message));
		}

		return;
	}

	/**
	 * Validates that the given 'created on attribute' indeed exists in our "owner".
	 * If not, it will log a message as well (depending on $this->useLogging...).
	 *
	 * @return bool
	 */
	private function _validateCreatedOnAttribute() {
		if ($this->owner->hasAttribute($this->createdOnAttrName)) {
			return true;
		}

		// if we're here then this validation test failed:
		if ($this->useLogging) {
			Yii::log("Error: \$createdOnAttrName is invalid (={$this->createdOnAttrName}) and do not exists in the given model (of class=" . get_class($this->owner) .
					"). Cannot do my work - aborting!", CLogger::LEVEL_ERROR, __METHOD__);
		}
		return false;
	}

	/**
	 * Determines if the 'created on attribute' value is valid and we can work with it, or not.
	 * If not, it will log a message as well (depending on $this->useLogging...).
	 *
	 * @return bool
	 */
	private function _validateCreateOnValue() {
		if ($this->_validateCreatedOnAttribute()) {
			$this->_initializeInternalAttributes();
		}
		else {
			// initial validation failed. no point in further checking.
			return false;
		}

		if (is_int($this->_ownerCreatedOnValue)) {
			return true;
		}
		// not int - try to instantiate a DateTime object. If we got one - we're cool (=valid).
		// $test is dropped at the end of this method run. A waste? I think its not that critical. This method will run selectively, only
		// on actual models that are asked to be edited by users - not for every request etc, so I don't think its a problem. Update me with
		// your thought if you think otherwise!
		$test = new DateTime($this->_ownerCreatedOnValue);
		if ($test !== false) {
			return true;
		}

		if ($this->useLogging) {
			Yii::log("Error: \$createdOnAttrName value ({$this->_ownerCreatedOnValue}) is invalid in the model I'm checking (type=" . get_class($this->owner) .
					"). Cannot do my work - aborting!", CLogger::LEVEL_ERROR, __METHOD__);
		}
		return false;
	}

	/**
	 * Convenience method: Initializes internal variables
	 */
	private function _initializeInternalAttributes() {
		$owner = $this->owner;
		$attr = $this->createdOnAttrName;
		$this->_ownerCreatedOnValue = $owner->$attr;
	}
}