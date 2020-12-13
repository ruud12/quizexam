<?php
/**
 * DokuWiki Plugin QuizExam (Syntax Component)
 *
 * @license GPL 2 http://www.gnu.org/licenses/gpl-2.0.html
 * @author  Ruud Habing <ruud.habing@lagersmit.com>
 */

// must be run within Dokuwiki
if (!defined('DOKU_INC')) {
	die();
}

class syntax_plugin_quizexam extends DokuWiki_Syntax_Plugin
{

	/**
	 * @return string Syntax mode type
	 */
	public function getType()
	{
		return 'formatting';
	}

	/**
	 * @return string Paragraph type */
	public function getPType()
	{
		return array('normal','block');
	}

	/**
	 * @return int Sort order - Low numbers go before high numbers
	 */
	public function getSort()
	{
		return 300;
	}

	/**
	 * Connect lookup pattern to lexer.
	 *
	 * @param string $mode Parser mode
	 */
	public function connectTo($mode)
	{
		$this->Lexer->addSpecialPattern('<quizexam>(?m).*?(?-m)</quizexam>', $mode, 'plugin_quizexam');
	}

	/** * Handle matches of the quizexam syntax
	 *
	 * @param string       $match   The match of the syntax
	 * @param int          $state   The state of the handler
	 * @param int          $pos     The position in the document
	 * @param Doku_Handler $handler The handler
	 *
	 * @return array Data for the renderer
	 */


	public function handle($match, $state, $pos, Doku_Handler $handler)
	{
		$data = array();
		$data['state'] = $state;
		$data['match'] = $match;
		$data['questions'] = array();


		$r = preg_split("/(\r\n|\n|\r)/", $match);
 
		$data['questions'] = array();
		$questions = 0;

		foreach($r as $q) {
			preg_match("/=+([\w\s\-\?]+)=+/", $q, $matched);
			preg_match("/\s*type\s+(.*)\s*/", $q, $type);
			if ($matched) {
				$questions++;
				$data['questions'][$questions] = array("question" => trim($matched[1]), "answers" => [], "rightanswers" => [], "type" => "");
			} elseif ($type) {
				$data['questions'][$questions]['type'] = trim($type[1]);
			} else {
				preg_match("/\s*\*(.*)/", $q, $not_correct);
				preg_match("/\s*\*\s*V\s+(.*)/", $q, $correct);
				if ($correct) {
					$data['questions'][$questions]['answers'][] = array("value" => trim($correct[1]), "correct" => true);
				} elseif ($not_correct) {
					$data['questions'][$questions]['answers'][] = array("value" => trim($not_correct[1]), "correct" => false);
				}
			}

		}


		return $data;
	}

	/**
	 * Render xhtml output or metadata
	 *
	 * @param string        $mode     Renderer mode (supported modes: xhtml)
	 * @param Doku_Renderer $renderer The renderer
	 * @param array         $data     The data from the handler() function
	 *
	 * @return bool If rendering was successful.
	 */



	public function render($mode, Doku_Renderer $renderer, $data)
	{     
		if ($mode == 'xhtml') {


			foreach($data['questions'] as $count => $question) {
				$renderer->doc .= "<b>Question: ".$question['question']."</b><br>";

				if ($question['type'] == 'text') {
					$renderer->doc .= "<input type='text'><br>";
				} elseif ($question['type'] == 'single') {
					foreach($question['answers'] as $option) {
						$renderer->doc .= "<input type='radio' id='".$question['question']."' value='".$option['value']."'>";
						$renderer->doc .= "<label for='".$question['question']."'> ".$option['value']."</label><br>";
					}
				}
			}
			print("<pre>");
			print_r($data['questions']);
			print("</pre>");


				

			return true;
		} else {
			return false;
		}
	}
}
