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
			preg_match("/=+([\w\s\-\?\:]+)=+/", $q, $matched);
			preg_match("/\s*type\s+(.*)\s*/", $q, $type);
			preg_match("/\s*quizid\s+(.*)\s*/", $q, $quizid);
			if ($quizid) {
				$data['quizid'] = $quizid[1];
			} elseif ($matched) {
				$questions++;
				$data['questions'][$questions] = array("question" => trim($matched[1]), "answers" => [], "type" => "", "answered_correctly" => 0, "answered_wrongly" => 0);
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
		$anything_answered = false;
		$total_correctly_answered = 0;

		for ($i=1; $i<count($data['questions'])+1; $i++) {
			$posted_answers = $_GET['question'.$i];
			if ($posted_answers) {
				$anything_answered = true;
			} else {
				$posted_answers = array();
			}

			if (!is_array($posted_answers)) {
				$posted_answers = array(trim($posted_answers));
			}
			$data['questions'][$i]['answered'] = $posted_answers;

			

			if (count($posted_answers) > 0) {
				if ($data['questions'][$i]['type'] == 'text') {
					$value = $data['questions'][$i]['answers'][0]['value'];
					if (in_array($value, $posted_answers)) {
						$data['questions'][$i]['answers'][0]['answered_correctly'] = true;
						$data['questions'][$i]['answered_correctly']++;
						$total_correctly_answered++;
					} else {
						$data['questions'][$i]['answers'][0]['answered_wrongly'] = true;
						$data['questions'][$i]['answered_wrongly']++;
					}
				} else {
					$temp_correct = 0;
					$temp_wrong = 0;
					foreach($data['questions'][$i]['answers'] as $k => $a) {
						if ($a["correct"]) {
							if (in_array($a["value"], $posted_answers)) {
								$data['questions'][$i]['answers'][$k]['answered_correctly'] = true;
								$data['questions'][$i]['answered_correctly']++;
								$temp_correct++;
							} else {
								$data['questions'][$i]['answers'][$k]['answered_wrongly'] = true;
								$data['questions'][$i]['answered_wrongly']++;
								$temp_wrong++;
							}
						} elseif (!$a["correct"] && in_array($a["value"], $posted_answers)) {
							$data['questions'][$i]['answers'][$k]['answered_wrongly'] = true;
							$data['questions'][$i]['answered_wrongly']++;
							$temp_wrong++;
						}
					}
					$total_correctly_answered += $temp_correct / ($temp_wrong + $temp_correct);
				}
			}
		}


		if ($mode == 'xhtml') {

			if ($data['quizid']) {
				$renderer->doc .= "<form action='".$_GET['ID']."' name='".str_replace(" ","",$data['quizid'])."' method='GET'>";
			} else {
				$renderer->doc .= "<form action='".$_GET['ID']."' method='GET'>";
			}

			if ($anything_answered) {
				$score = round(($total_correctly_answered/count($data['questions']))*100,1);

				if ($score > 50) {
					$color = 'lightgreen';
				} else {
					$color = 'red';
				}
				$renderer->doc .= "<div style='background-color:".$color."'>Score: ".$score."%</div><br><br>";
			}	

			$renderer->doc .= "<input type='hidden' name='id' value='".$_GET['id']."'>";

			foreach($data['questions'] as $q_count => $question) {
				$renderer->doc .= "<b>".$q_count.": ".$question['question']."</b> ";


				if ($anything_answered) {
					if ($question['answered_correctly'] > 0 && $question['answered_wrongly'] == 0) {
						$renderer->doc .= "<span style='color:green'>Goed beantwoord</span><br>";
					} elseif ($question['answered_correctly'] == 0 && $question['answered_wrongly'] == 0) {
						$renderer->doc .= "<span style='color:orange'>Niet beantwoord</span><br>";
					} else {
						$renderer->doc .= "<span style='color:red'>Fout beantwoord</span><br>";
					}
				} else {
					$renderer->doc .= "<br>";
				}


				if ($question['type'] == 'text') {
					$renderer->doc .= "<input type='text' name='question".$q_count."' value='".$question['answered'][0]."'><br>";
				} elseif ($question['type'] == 'single') {
					foreach($question['answers'] as $count => $option) {
						if (in_array($option['value'], $question['answered'])) {
							$checked = "checked='checked'";
						} else {
							$checked = "";
						}

						if ($anything_answered) {
							if ($option['answered_correctly'] || $option['correct']) {
								$color = "style='color:green;font-weight:bold'";
							} elseif ($option['answered_wrongly']) {
								$color = "style='color:red'";
							} else {
								$color = "";
							}
						}
							
						$renderer->doc .= "<input ".$checked." type='radio' id='question_".$q_count."_".$count."' name='question".$q_count."' value='".$option['value']."'>";
						$renderer->doc .= "<label ".$color." for='question_".$q_count."_".$count."'> ".$option['value']."</label><br>";
					}
				} elseif ($question['type'] == 'multi') {
					foreach($question['answers'] as $count => $option) {

						if (in_array($option['value'], $question['answered'])) {
							$checked = "checked='checked'";
						} else {
							$checked = "";
						}


						if ($anything_answered) {
							if ($option['answered_correctly'] || $option['correct']) {
								$color = "style='color:green;font-weight:bold'";
							} elseif ($option['answered_wrongly']) {
								$color = "style='color:red'";
							} else {
								$color = "";
							}
						}
						$renderer->doc .= "<input ".$checked." type='checkbox' id='question_".$q_count."_".$count."' name='question".$q_count."[]' value='".$option['value']."'>";
						$renderer->doc .= "<label ".$color." for='question_".$q_count."_".$count."'> ".$option['value']."</label><br>";
					}

				}
				$renderer->doc .= "<br>";
			}

			$renderer->doc .= "<input type='submit' value='Submit'>";
			$renderer->doc .= "</form>";

			//print("<pre>");
			//print_r($data['questions']);
			//print("</pre>");
				

			return true;
		} else {
			return false;
		}
	}
}

