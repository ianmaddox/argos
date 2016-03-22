<?php
/**
 * Description of cliguiclass
 *
 * @author ianmaddox
 *
 * @package framework
 * @subpackage util
 */
class util_cliGui {
	private $screenSizeAvailable = false;
	private $winWidth = false;
	private $winHeight = false;
	private $rowCaret = 1;
	private $elementLeftPad = 3;
	private $btnLeftPad = 3;
	private $btnRightPad = 6;
	private $helpLine = false;

	private $form = false;
	private $formElements = array();
	private $formData;
	private $formUsed = false;
	private $formInputs = 0;

	private $lastRadio;

	const POSITION_CENTER = true;
	const POSITION_HOME = false;

	const TYPE_BUTTON = 'button';
	const TYPE_LISTBOX = 'list';
	const TYPE_RADIO = 'radio';
	const TYPE_LABEL = 'label';
	const TYPE_TEXT = 'text';

	/**
	 * Constructor
	 *
	 * @param string $title
	 */
	public function __construct($title = false) {
		newt_init();
		$this->clear();
		newt_get_screen_size($this->winWidth, $this->winHeight);

		if($this->winWidth > 0 && $this->winHeight > 0) {
			$this->screenSizeAvailable = true;
		}

		if($title) {
			$this->helpLine = true;
			newt_draw_root_text(0, 0, $title);
		}
		newt_push_help_line(null);
	}

	/**
	 * Clear the screen
	 */
	public function clear() {
		newt_cls();
		newt_refresh();
	}

	/**
	 * Create a new form
	 */
	private function newForm () {
		$this->form = newt_form();
		$this->formUsed = true;
	}

	/**
	 * Prepare a form
	 */
	private function prepForm() {
		if(empty($this->form)) {
			$this->newForm();
		}
	}

	/**
	 * Wipe the form
	 */
	private function wipeForm() {
		newt_form_destroy($this->form);
		$this->form = false;
		$this->formElements = array();
		$this->formInputs = 1;
	}

	/**
	 * Add an element to the form
	 *
	 * @param mixed $element
	 * @param string $type
	 * @param int $id
	 * @param string $altVal
	 */
	private function addFormElement($element, $type, $id = false, $altVal = false) {
		if(!$id) {
			$id = count($this->formElements);
		}
		$this->prepForm();

		if(is_array($element)) {
			newt_form_add_components($this->form, $element);
		} else {
			newt_form_add_component($this->form, $element);
		}

		if($type != self::TYPE_LABEL) {
			$this->formInputs++;
		}

		if($type == self::TYPE_RADIO) {
			if(!isset($this->formElements[$id])) {
				$this->formElements[$id] = array('type' => $type, 'element' => array());
			}
			$this->formElements[$id]['element'][$altVal] = $element;
		} else {
			$this->formElements[$id] = array('element' => $element, 'type' => $type);
		}
	}

	/**
	 * Get the coordinates
	 *
	 * @param int $xPos
	 * @param int $yPos
	 * @param int $rows
	 * @param int $cols
	 * @return array
	 */
	private function getCoords($xPos, $yPos, $rows, $cols) {
		$x = $xPos == self::POSITION_HOME ? 1 : $xPos;
		$y = $yPos == self::POSITION_HOME ? 1 : $yPos;

		if($this->screenSizeAvailable && $x == self::POSITION_CENTER) {
			$x = ($this->winWidth - $cols) / 2;
		}

		if($this->screenSizeAvailable && $y == self::POSITION_CENTER) {
			$y = ($this->winHeight - $rows) / 2;
		}

		return array($x,$y, 'x' => $x, 'y' => $y);
	}

	/**
	 * Add a window
	 *
	 * @param string $title
	 * @param int $rows
	 * @param int $cols
	 * @param int $xPos
	 * @param int $yPos
	 */
	public function addWindow($title, $rows, $cols, $xPos = self::POSITION_HOME, $yPos = self::POSITION_HOME) {
		list($xPos, $yPos) = $this->getCoords($xPos, $yPos, $rows, $cols);
		newt_open_window($xPos, $yPos, $cols, $rows, $title);
		$this->resetRowCaret();
	}

	/**
	 * Add a list box to the form
	 *
	 * @param int $id
	 * @param array $elements
	 * @param int $rows
	 */
	public function addListbox($id, $elements, $rows = false) {
//		$width = 1;
		if($rows != false) {
			$rows = count($elements) > $rows ? $rows : count($elements);
		} else {
			$rows = count($elements);
		}
//		foreach($elements as $item) {
//			$width = strlen($item) > $width ? strlen($item) : $width;
//		}

		$element = newt_listbox($this->elementLeftPad - 1, $this->rowCaret, $rows + 2, NEWT_FLAG_BORDER);

		foreach($elements as $item) {
			newt_listbox_add_entry($element, $item, $item);
		}
		$this->moveRowCaret($rows + 3);
		$this->addFormElement($element, self::TYPE_LISTBOX, $id);
	}

	/**
	 * Add a checkbox
	 *
	 * @param int $id
	 */
	public function addCheckbox($id) {

	}

	/**
	 * Add a button to the form
	 *
	 * @param int $id
	 * @param mixed $labels
	 */
	public function addButton($id, $labels) {

		if(is_array($labels)) {
			$element = array();
			$pos = $this->btnLeftPad;
			foreach($labels as $val) {
				$element[$val] = newt_button($pos, $this->rowCaret, $val);
				$pos = $pos + $this->btnRightPad + strlen($val);
			}
		} else {
			$element = newt_button($this->btnLeftPad, $this->rowCaret, $labels);
		}
		$this->addFormElement($element, self::TYPE_BUTTON, $id);
		$this->moveRowCaret(5);
	}

	/**
	 * Add a radio button to the form
	 *
	 * @param int $id
	 * @param string $label
	 * @param bool $default
	 * @param bool $newSet
	 */
	public function addRadio($id, $label, $default = false, $newSet = false) {
		$prevButton = false;

		// If we are not starting a new radio button set and we have a previous radio button defined,
		// send that one in as the 5th argument to newt_radiobutton().
		// This function does not accept anything other than an element resourceID for the 5th arg, so
		// we must have two calls: one with and one without.
		if(!$newSet && !empty($this->lastRadio)) {
			$element = newt_radiobutton($this->elementLeftPad, $this->rowCaret, $label, $default, $this->lastRadio);
		} else {
			$element = newt_radiobutton($this->elementLeftPad, $this->rowCaret, $label, $default);
		}
		$this->addFormElement($element, self::TYPE_RADIO, $id, $label);
		$this->lastRadio = $element;
		$this->moveRowCaret(2);
	}

	/**
	 * Add a text box to the form
	 *
	 * @param int $id
	 * @param string $label
	 * @param string $value
	 * @param bool $password
	 * @param int $width
	 */
	public function addTextbox($id, $label, $value = '', $password = false, $width = 20) {
		$element = newt_label($this->elementLeftPad, $this->rowCaret, $label);
		$this->moveRowCaret();
		$this->addFormElement($element, self::TYPE_LABEL);
		$flags = $password ? NEWT_FLAG_PASSWORD + NEWT_FLAG_SHOWCURSOR : NEWT_FLAG_SHOWCURSOR;
		$element = newt_entry($this->elementLeftPad, $this->rowCaret, $width, $value, $flags);

		$this->moveRowCaret(2);
		$this->addFormElement($element, self::TYPE_TEXT, $id);
	}

	/**
	 * Add a label
	 *
	 * @param string $text
	 */
	public function addLabel($text) {
		$rows = explode("\n", trim($text));
		foreach($rows as $row) {
			$element = newt_label($this->elementLeftPad, $this->rowCaret, $row);
			$this->addFormElement($element, self::TYPE_LABEL);
			$this->moveRowCaret(1);
		}
		$this->moveRowCaret();
	}

	/**
	 * Draw the form
	 *
	 * @param bool $clearAfter
	 */
	public function draw($clearAfter = true) {
		newt_refresh();

		if($this->formInputs > 0) {
			newt_form_run($this->form, $this->formResult);
		}

		newt_pop_window();

		if($this->helpLine == true) {
			newt_pop_help_line();
		}

		if($this->form) {
			$this->formData = $this->gatherFormdata();
			$this->wipeForm();
		}

		if($clearAfter) {
			$this->clear();
		}
	}

	/**
	 * Finish up
	 */
	public function cleanup() {
		newt_finished();
	}

	/**
	 * Get the data from the form
	 *
	 * @return mixed
	 */
	public function getFormdata() {
		return $this->formData;
	}

	/**
	 * Put the form data into an array and return it
	 *
	 * @return array
	 */
	private function gatherFormData() {
		$data = array();
		foreach($this->formElements as $id => $el) {
			if($el['type'] == self::TYPE_LABEL) {
				continue;
			}
			$data[$id] = $this->getElementdata($el['element'], $el['type']);
		}
		return $data;
	}

	/**
	 * Get the data for an element
	 *
	 * @param string $element
	 * @param string $type
	 * @return mixed
	 */
	private function getElementData($element, $type) {
		switch($type) {
			case self::TYPE_BUTTON:
				$offset = false;
				if(!is_array($element)) {
					$element = array($element);
				}
				foreach($element as $key => $el) {
					if($el == $this->formResult['component']) {
						return $key;
					}
				}
				return false;
				break;

			case self::TYPE_TEXT:
				return newt_entry_get_value($element);
				break;

			case self::TYPE_LISTBOX:
				return newt_listbox_get_current($element);
				break;

			case self::TYPE_RADIO:
				foreach($element as $key => $el) {
					// Find the active radio element and return its numerical index.
					if($el == newt_radio_get_current($el)) {
						return $key;
					}
				}
				break;

			case self::TYPE_LABEL:
				return false;
				break;

			default:
				trigger_error("Invalid element type specified: $type",E_USER_ERROR);
				break;
		}
	}

	/**
	 * Move the row caret
	 *
	 * @param int $rowsDown
	 */
	private function moveRowCaret($rowsDown = 1) {
		$this->rowCaret += (int)$rowsDown;
	}

	/**
	 * Reset the row caret
	 */
	private function resetRowCaret() {
		$this->rowCaret = 1;
	}
}
