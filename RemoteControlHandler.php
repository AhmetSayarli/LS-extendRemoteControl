<?php

/**
 * Handler for extendRemoteControl Plugin for LimeSurvey : add yours functions here
 *
 * @author Denis Chenu <denis@sondages.pro>
 * @copyright 2015-2016 Denis Chenu <http://sondages.pro>
 * @license GPL v3
 * @version 1.0
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 */
class RemoteControlHandler extends remotecontrol_handle
{
    /**
     * @inheritdoc
     * Disable webroute else json returned can be broken
     */
    public function __construct(AdminController $controller)
    {
        /* Deactivate web log */
        foreach (Yii::app()->log->routes as $route) {
            $route->enabled = $route->enabled && !($route instanceof CWebLogRoute);
        }
        parent::__construct($controller);
    }
    /**
     * RPC Routine to get information on user from extendRemoteControl plugin
     *
     * @access public
     * @param string $sSessionKey Auth credentials
     * @return array The information on user (except password)
     */
    public function get_me($sSessionKey)
    {
        if ($this->_checkSessionKey($sSessionKey)) {
            $oUser = User::model()->find("uid=:uid", array(":uid" => Yii::app()->session['loginID']));
            if ($oUser) // We have surely one, else no sessionkey ....
            {
                $aReturn = $oUser->attributes;
                unset($aReturn['password']);
                return $aReturn;
            }
        }
    }

    /**
     * RPC Routine to get global permission of the actual user
     *
     * @access public
     * @param string $sSessionKey Auth credentials
     * @param string $sPermission string Name of the permission - see function getGlobalPermissions
     * @param $sCRUD string The permission detailsyou want to check on: 'create','read','update','delete','import' or 'export'
     * @return bool True if user has the permission
     * @return boolean
     */
    public function hasGlobalPermission($sSessionKey, $sPermission, $sCRUD = 'read')
    {
        $this->_checkSessionKey($sSessionKey);
        return array(
            'permission' => Permission::model()->hasGlobalPermission($sPermission, $sCRUD)
        );
    }

    /**
     * RPC Routine to get survey permission of the actual user
     *
     * @access public
     * @param string $sSessionKey Auth credentials
     * @param $iSurveyID integer The survey ID
     * @param $sPermission string Name of the permission
     * @param $sCRUD string The permission detail you want to check on: 'create','read','update','delete','import' or 'export'
     * @return bool True if user has the permission
     * @return boolean
     */
    public function hasSurveyPermission($sSessionKey, $iSurveyID, $sPermission, $sCRUD = 'read')
    {
        $this->_checkSessionKey($sSessionKey);
        return array(
            'permission' => \Permission::model()->hasSurveyPermission($iSurveyID, $sPermission, $sCRUD),
        );
    }
    /**
     * Get survey properties (RPC function)
     *
     * Get properties of a survey
     * All internal properties of a survey are available.
     * @see \Survey for the list of available properties
     *
     * Failure status : Invalid survey ID, Invalid session key, No permission, No valid Data
     *
     * @access public
     * @param string $sSessionKey Auth credentials
     * @param int $iSurveyID The id of the Survey to be checked
     * @return array
     */
    public function get_survey_quotas($sSessionKey, $iSurveyID)
    {
        Yii::app()->loadHelper("surveytranslator");
        if ($this->_checkSessionKey($sSessionKey)) {
            $iSurveyID = (int) $iSurveyID;
            $oSurvey = Survey::model()->findByPk($iSurveyID);
            if (!isset($oSurvey)) {
                return array('status' => 'Error: Invalid survey ID');
            }
            if (Permission::model()->hasSurveyPermission($iSurveyID, 'surveysettings', 'read')) {
                $quotas = $oSurvey->quotas;
                $aResult = array();
                foreach ($quotas as $quota) {
                    // var_dump($quota); 
                    $completedCount = $quota->getCompleteCount();
                    $quotaRes = $quota->getAttributes();
                    $quotaRes['completed'] = $completedCount;
                    // var_dump($completedCount);
                    $aResult[] = $quotaRes;
                }
                return $aResult;
            } else {
                return array('status' => 'No permission');
            }
        } else {
            return array('status' => 'Invalid Session key');
        }
    }

    public function get_answers_count($sSessionKey, $iSurveyID)
    {
        Yii::app()->loadHelper("surveytranslator");
        if ($this->_checkSessionKey($sSessionKey)) {
            $iSurveyID = (int) $iSurveyID;
            $oSurvey = Survey::model()->findByPk($iSurveyID);
            if (!isset($oSurvey)) {
                return array('status' => 'Error: Invalid survey ID');
            }
            $fullAnswersCount = $oSurvey->getCountFullAnswers();
            $partialAnswersCount = $oSurvey->getCountPartialAnswers();
            $oResponse['fullanswers'] = $fullAnswersCount;
            $oResponse['partialanswers'] = $partialAnswersCount;
            return ($oResponse);
        } else {
            return array('status' => 'Invalid Session key');
        }
    }
    public function get_answers_by_questionid($sSessionKey, $iSurveyID, $questionId)
    {
        Yii::app()->loadHelper("surveytranslator");
        if ($this->_checkSessionKey($sSessionKey)) {
            $iSurveyID = (int) $iSurveyID;
            $oSurvey = Survey::model()->findByPk($iSurveyID);
            if (!isset($oSurvey)) {
                return array('status' => 'Error: Invalid survey ID');
            }
            $table = $oSurvey->responsesTableName;
            if (method_exists(Yii::app()->cache, 'flush')) {
                Yii::app()->cache->flush();
            }
            if (!Yii::app()->db->schema->getTable($table)) {
                return null;
            } else {
                $answers = Yii::app()->db->createCommand()
                    ->select($questionId)
                    ->from($table)
                    ->queryAll();
                return $answers;
            }

            return ($table);
        } else {
            return array('status' => 'Invalid Session key');
        }
    }
    public function get_question_attributes_of_survey($sSessionKey, $iSurveyID)
    {
        Yii::app()->loadHelper("surveytranslator");
        if ($this->_checkSessionKey($sSessionKey)) {
            $iSurveyID = (int) $iSurveyID;
            $oSurvey = Survey::model()->findByPk($iSurveyID);
            if (!isset($oSurvey)) {
                return array('status' => 'Error: Invalid survey ID');
            }
            $language = 'en';

            $query = "SELECT questions.*, question_groups.group_name, question_groups.group_order"
                . " FROM {{questions}} as questions, " . Yii::app()->db->quoteTableName('{{groups}}') . " as question_groups"
                . " WHERE question_groups.gid=questions.gid"
                . " AND question_groups.language=:language1"
                . " AND questions.language=:language2"
                . " AND questions.parent_qid=0"
                . " AND questions.sid=:sid";
            $questions = Yii::app()->db->createCommand($query)
                ->bindParam(":language1", $language, PDO::PARAM_STR)
                ->bindParam(":language2", $language, PDO::PARAM_STR)
                ->bindParam(":sid", $iSurveyID, PDO::PARAM_INT)->queryAll();

            $aResult = array();
            $index = 0;
            foreach ($questions as $aQuestion) {
                $tempQuestion = $aQuestion;
                $oSubQuestions = Question::model()->findAllByAttributes(array('parent_qid' => $aQuestion['qid'], 'language' => $language), array('order' => 'title'));
                if (count($oSubQuestions) > 0) {
                    $aData = array();
                    foreach ($oSubQuestions as $oSubQuestion) {

                        $aData[$oSubQuestion['qid']]['title'] = $oSubQuestion['title'];
                        $aData[$oSubQuestion['qid']]['question'] = $oSubQuestion['question'];
                        $aData[$oSubQuestion['qid']]['scale_id'] = $oSubQuestion['scale_id'];
                    }
                    $tempQuestion['subquestions'] = $aData;
                } else {
                    $tempQuestion['subquestions'] = 'No available answers';
                }
                if (count($oSubQuestions) > 0) {
                    $aData = array();
                    foreach ($oSubQuestions as $oSubQuestion) {
                        $aData[$oSubQuestion['title']] = $oSubQuestion['question'];
                    }

                    $tempQuestion['available_answers'] = $aData;
                } else {
                    $tempQuestion['available_answers']  = 'No available answers';
                }
                $oAttributes = QuestionAttribute::model()->findAllByAttributes(array('qid' => $aQuestion['qid'], 'language' => $language), array('order' => 'attribute'));
                if (count($oAttributes) > 0) {
                    $aData = array();
                    foreach ($oAttributes as $oAttribute) {
                        $aData[$oAttribute['attribute']] = $oAttribute['value'];
                    }

                    $tempQuestion['attributes'] = $aData;
                } else {
                    $tempQuestion['attributes'] = 'No available attributes';
                }
                $oAttributes = QuestionAttribute::model()->findAllByAttributes(array('qid' => $aQuestion['qid'], 'language' => $language), array('order' => 'attribute'));
                if (count($oAttributes) > 0) {
                    $aData = array();
                    foreach ($oAttributes as $oAttribute) {
                        $aData[$oAttribute['attribute']] = $oAttribute['value'];
                    }

                    $tempQuestion['attributes_lang'] = $aData;
                } else {
                    $tempQuestion['attributes_lang'] = 'No available attributes';
                }
                $oAttributes = Answer::model()->findAllByAttributes(array('qid' => $aQuestion['qid'], 'language' => $language), array('order' => 'sortorder'));
                if (count($oAttributes) > 0) {
                    $aData = array();
                    foreach ($oAttributes as $oAttribute) {
                        $aData[$oAttribute['code']]['answer'] = $oAttribute['answer'];
                        $aData[$oAttribute['code']]['assessment_value'] = $oAttribute['assessment_value'];
                        $aData[$oAttribute['code']]['scale_id'] = $oAttribute['scale_id'];
                        $aData[$oAttribute['code']]['order'] = $oAttribute['sortorder'];
                    }
                    $tempQuestion['answeroptions'] = $aData;
                } else {
                    $tempQuestion['answeroptions'] = 'No available answer options';
                }
                $aResult[] = $tempQuestion;
                $index = $index + 1;
            }
            return $aResult;
        } else {
            return array('status' => 'Invalid Session key');
        }
    }

    public function delete_response_by_id($sSessionKey, $iSurveyID, $iResponseID)
    {
        Yii::app()->loadHelper("surveytranslator");
        if ($this->_checkSessionKey($sSessionKey)) {
            $iSurveyID = (int) $iSurveyID;
            $oSurvey = Survey::model()->findByPk($iSurveyID);

            if (!isset($oSurvey)) {
                return array('status' => 'Error: Invalid survey ID');
            }
            $language = 'en';
            $response = Response::model($iSurveyID);
            return $response;

            //Response::model($iSurveyID)->deleteByPk($responseId);
        } else {
            return array('status' => 'Invalid Session key');
        }
    }
}
