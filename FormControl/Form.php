<?php
namespace OCC2\control;
/**
 * Exception of Form Control
 * @author Milan Onderka
 * @category Exceptions
 * @package OCC2
 * @version 1.0.0
 */
class FormException extends \Exception{}

/**
 * Form control 
 * @author Milan Onderka
 * @category Controls
 * @package OCC2
 * @version 1.0.0
 */
class FormControl extends \Nette\Application\UI\Control{
    const DEFAULT_TEXT_LENGTH=20;
    const DEFAULT_TEXT_MAXLENGTH=100;    
    const DEFAULT_TEXTAREA_COLS=20;
    const DEFAULT_TEXTAREA_ROWS=10;
     
    
    /**
     * @var \IDBModel
     */
    public $source;
    
    /**
     * @var array
     */
    public $schema=[];
    
    /**
     * @var mixed
     */
    public $values=null;
    
    /**
     * @var array
     */
    public $list=[];
    
    /**
     * @var integer
     */
    public $id=null;
    
    public $translator=null;
    
    /**
     * constructor
     * @param \BaseModule\IDBModel $source
     * @param \Kdyby\Translation\ITranslator $translator
     * @return void
     */
    public function __construct(\BaseModule\IDBModel $source, \Kdyby\Translation\ITranslator $translator=null){
        $this->source = $source;
        $this->schema=$this->source->getConfig()["formSchema"];
        $translator==null ? false : $this->setTranslator($translator);
        return;
    }
    
    /**
     * @param \Kdyby\Translation\ITranslator $translator
     * @return void
     */
    public function setTranslator(\Kdyby\Translation\ITranslator $translator){
        $this->schema["translate"]==1 ? $this->translator = $translator:false;
        return;
    }
    
    /**
     * load values to form
     * @param mixed $id
     * @return void
     */
    public function loadValues($id){
        $this->values = $this->source->loadItem($id,true);
        $this->id=$id;
        return;
    }
    
    /**
     * render control
     * @return void
     */
    public function render(){
        $this->translator==null ? false : $this->template->translatable=1;
        $this->template->formName=$this->schema["formName"];
        
        isset($this->schema["formInPanel"])? $this->template->formInPanel = $this->schema["formInPanel"]:false;
        isset($this->schema["panelType"])? $this->template->panelType = $this->schema["panelType"]:false;
        isset($this->schema["isHidable"]) ? $this->template->isHidable = $this->schema["isHidable"]:false;
        isset($this->schema["isAjaxForm"]) ? $this->template->isAjaxForm = $this->schema["isAjaxForm"]:false;
        isset($this->schema["additionalButtons"]) and count($this->schema["additionalButtons"])>0 ? $this->template->addButtons = $this->schema["additionalButtons"]:false;
        
        if(isset($this->schema["formTitle"])){
            $this->translator==null ? $this->template->formTitle = $this->schema["formTitle"] : $this->template->formTitle = $this->translator->translate($this->schema["formTitle"]);
        }
        
        if(isset($this->schema["formFooterText"])){
            $this->translator==null ? $this->template->formFooterText = $this->schema["formFooterText"]: $this->template->formFooterText = $this->translator->translate($this->schema["formFooterText"]);
        }
        
        if(isset($this->schema["additionalText"])){
            $this->translator==null ? $this->template->additionalText = $this->schema["additionalText"]: $this->template->additionalText = $this->translator->translate($this->schema["additionalText"]);
        }
        
        $this->template->render(__DIR__ . '/form.latte');
        return;
    }
    
    /**
     * factory to create form component
     * @return \Nette\Application\UI\Form
     * @throws FormException
     */
    public function createComponentForm(){
        $form = new \Nette\Application\UI\Form();
        $this->translator==null ? false : $form->setTranslator($this->translator);        
        (isset($this->schema["useBootstrap"]) and $this->schema["useBootstrap"]==1) ? $form->setRenderer(new \Tomaj\Form\Renderer\BootstrapRenderer()) : false;

        if(isset($this->schema["elements"]) and $this->schema["elements"]>0){
            foreach($this->schema["elements"] as $name=>$params){
                switch ($params["type"]) {
                    case "text":
                        $this->addText($form,$name, $params);
                        break;
                    case "password":
                        $this->addPassword($form,$name, $params);
                        break;
                    case "textarea":
                        $this->addTextarea($form,$name, $params);
                        break;
                    case "email":
                        $this->addEmail($form,$name, $params);
                        break;
                    case "upload":
                        $this->addUpload($form,$name, $params);
                        break;
                    case "multiupload":
                        $this->addMultiupload($form,$name, $params);
                        break;
                    case "hidden":
                        $this->addHidden($form,$name, $params);
                        break;
                    case "checkbox":
                        $this->addCheckBox($form,$name, $params);
                        break;
                    case "checkboxlist":
                        $this->addCheckBoxList($form,$name, $params);
                        break;
                    case "radiolist":
                        $this->addRadiolist($form,$name, $params);
                        break;
                    case "select":
                        $this->addSelect($form,$name, $params);
                        break;
                    case "multiselect":
                        $this->addMultiselect($form,$name, $params);
                        break;
                    case "recaptcha":
                        $this->addReCaptcha($form, $name, $params);
                        break;
                    default:
                        throw new \BaseModule\FormException("base.controls.form.invalidElementType");
                }
            }
        }
        
        ($this->id!=null or $this->id!="")? $form->addHidden($this->source->getPrimary(), $this->id):false;
        
        
        isset($this->schema["submitButtonText"]) ? false : $this->schema["submitButtonText"]="Save";
        
        $form->addSubmit("save",$this->schema["submitButtonText"]);
        $form->onSuccess[] = [$this, 'formSucceed'];
        return $form;
    }
    
    /**
     * sucessfull send form handler
     * @param \Nette\Application\UI\Form $form
     */
    public function formSucceed(\Nette\Application\UI\Form $form){
        $values = $form->getValues(true);
        $redirection = isset($this->schema["redirectTo"]) ? $this->schema["redirectTo"] : "this";
        try {
            if(isset($this->schema["modelMethod"])){
                call_user_method($this->schema["modelMethod"], $this->source, $values);
            }
            else{
                $this->source->saveItem($values);
            }
            
            isset($this->schema["successMessage"]) ? false : $this->schema["successMessage"] = "base.controls.form.dataSaved";
            $message = $this->translator==null ? $this->schema["successMessage"] : $this->translator->translate($this->schema["successMessage"]);

            $this->getPresenter()->flashMessage($message, "success");
            
            (!isset($this->schema["isAjaxForm"]) || $this->schema["isAjaxForm"]!=1) ? $this->redirect($redirection) : $this->redrawControl();
                        
        } catch (\BaseModule\IDBModelException $exc) {
            $message = $this->translator ==null ? $exc->getMessage() : $this->translator->translate($exc->getMessage());
            $this->getPresenter()->flashMessage($message,"danger");
            (!isset($this->schema["isAjaxForm"]) || $this->schema["isAjaxForm"]!=1)? $this->redirect($redirection) : $this->redrawControl();            
        }
    }
    
    /**
     * set value list of select, checkbox and radioboxes
     * @param string $key
     * @param array $list
     * @return \occ2\Form
     */
    public function setList($key,$list){
        $this->list[$key]=$list;
        return $this;
    }
    
    /**
     * handle new item form
     * @return void
     */
    public function handleNew(){
        $this->values=[];
        $this->schema["isAjaxForm"] ? $this->redrawControl("form") : $this->redirect("this");
        return;        
    }
    
    /**
     * handle Edit form
     * @param mixed $id
     * @return void
     */
    public function handleEdit($id){
        $this->values = $this->source->loadItem($id, true);
        $this->schema["isAjaxForm"] ? $this->redrawControl("form") : $this->redirect("this");
        return;
    }
    
    /**
     * @param object $element
     * @param array $rule
     */
    protected function addRule($element,$rule){
        if($rule["rule"]==":equal"){
            $pat = $rule["patterns"];
            $form=$element->getForm();
            $el=$form->getComponent($pat);
            $rule["patterns"] = $el;
        }       
        $element->addRule($rule["rule"],$rule["message"],$rule["patterns"]);
    }
    
    /**
     * @param \Nette\Application\UI\Form $form
     * @param strimg $name
     * @param params $params
     * @return \Nette\Application\UI\Form
     */
    protected function addText(\Nette\Application\UI\Form $form,$name,$params=[]){
        isset($params["title"]) ? false:$params["title"]=$name;
        isset($params["cols"]) ? false:$params["cols"]=self::DEFAULT_TEXT_LENGTH;
        isset($params["maxlength"]) ? false:$params["maxlength"]=self::DEFAULT_TEXT_MAXLENGTH;
        
        $element = $form->addText($name, $params["title"], $params["cols"], $params["maxlength"]);

        isset($params["defaultValue"]) ? $element->setDefaultValue($params["defaultValue"]):false;
        (isset($params["disabled"]) and $params["disabled"]==1) ? $element->setDisabled():false;
        isset($this->values[$name]) ? $element->setValue($this->values[$name]):false;
        
        if(isset($params["isRequired"]) and $params["isRequired"]==1){
            $this->translator==null ? $element->setRequired("Element " . $params["title"] . " must be filled"):$element->setRequired($this->translator->translate("base.controls.form.emptyElementWarning",["element"=>$this->translator->translate($params["title"])]));
        }

        if(isset($params["rules"]) and count($params["rules"])>0){
            foreach ($params["rules"] as $rule){
                isset($rule["patterns"]) ? false : $rule["patterns"]=null;
                $this->addRule($element, $rule);
            }
        }
        return $form;
    }
    
    /**
     * @param \Nette\Application\UI\Form $form
     * @param string $name
     * @param array $params
     * @return \Nette\Application\UI\Form
     */
    protected function addTextarea(\Nette\Application\UI\Form $form,$name,$params=[]){
        isset($params["title"]) ? false : $params["title"]=$name;
        isset($params["cols"]) ? false : $params["cols"]=self::DEFAULT_TEXTAREA_COLS;
        isset($params["rows"]) ? false : $params["rows"]=self::DEFAULT_TEXTAREA_ROWS;
        
        $element = $form->addTextArea($name, $params["title"], $params["cols"], $params["rows"]);
        
        isset($params["defaultValue"]) ? $element->setDefaultValue($params["defaultValue"]) : false;
        (isset($params["disabled"]) and $params["disabled"]==1) ? $element->setDisabled() : false;
        isset($this->values[$name]) ? $element->setValue($this->values[$name]) : false;
        
        if(isset($params["isRequired"]) and $params["isRequired"]==1){
            $this->translator==null ? $element->setRequired("Element " . $params["title"] . " must be filled") : $element->setRequired($this->translator->translate("base.controls.form.emptyElementWarning",["element"=>$this->translator->translate($params["title"])])); 
        }        
       
        if(isset($params["rules"]) and count($params["rules"])>0){
            foreach ($params["rules"] as $rule){
                isset($rule["patterns"]) ? false : $rule["patterns"]=null;
                $this->addRule($element, $rule);
            }
        }
        return $form;
    }
    
    /**
     * @param \Nette\Application\UI\Form $form
     * @param string $name
     * @param array $params
     * @return \Nette\Application\UI\Form
     */
    protected function addPassword(\Nette\Application\UI\Form $form,$name,$params=[]){
        isset($params["title"])? false: $params["title"]=$name;
        isset($params["cols"]) ? false : $params["cols"]=self::DEFAULT_TEXT_LENGTH;
        isset($params["maxlength"]) ? false : $params["maxlength"]=self::DEFAULT_TEXT_MAXLENGTH;
        
        $element = $form->addPassword($name, $params["title"], $params["cols"], $params["maxlength"]);
        
        isset($params["defaultValue"]) ? $element->setDefaultValue($params["defaultValue"]) : false;
        (isset($params["disabled"]) and $params["disabled"]==1) ? $element->setDisabled(): false;
        isset($this->values[$name]) ? $element->setValue($this->values[$name]) : false;        
        if(isset($params["isRequired"]) and $params["isRequired"]==1){
            $this->translator==null ? $element->setRequired("Element " . $params["title"] . " must be filled"): $element->setRequired($this->translator->translate("base.controls.form.emptyElementWarning",["element"=>$this->translator->translate($params["title"])])); 
        }

        if(isset($params["rules"]) and count($params["rules"])>0){
            foreach ($params["rules"] as $rule){
                isset($rule["patterns"]) ? false : $rule["patterns"]=null;
                $this->addRule($element, $rule);
            }
        }
        return $form;
    }
    
    /**
     * @param \Nette\Application\UI\Form $form
     * @param string $name
     * @param array $params
     * @return \Nette\Application\UI\Form
     */
    protected function addEmail(\Nette\Application\UI\Form $form,$name,$params=[]){
        isset($params["title"])? false: $params["title"]=$name;
        isset($params["cols"]) ? false : $params["cols"]=self::DEFAULT_TEXT_LENGTH;
        
        $element = $form->addText($name, $params["title"],$params["cols"]);
        $element->addRule(":email");
        
        isset($params["defaultValue"]) ? $element->setDefaultValue($params["defaultValue"]) : false;
        (isset($params["disabled"]) and $params["disabled"]==1) ? $element->setDisabled(): false;
        isset($this->values[$name]) ? $element->setValue($this->values[$name]) : false; 
        
        if(isset($params["isRequired"]) and $params["isRequired"]==1){
            $this->translator==null ? $element->setRequired("Element " . $params["title"] . " must be filled"): $element->setRequired($this->translator->translate("base.controls.form.emptyElementWarning",["element"=>$this->translator->translate($params["title"])])); 
        }
        if(isset($params["rules"]) and count($params["rules"])>0){
            foreach ($params["rules"] as $rule){
                isset($rule["patterns"]) ? false : $rule["patterns"]=null;
                $this->addRule($element, $rule);
            }
        }
        return $form;
    }
    
    /**
     * @param \Nette\Application\UI\Form $form
     * @param string $name
     * @param array $params
     * @return \Nette\Application\UI\Form
     */
    protected function addReCaptcha(\Nette\Application\UI\Form $form,$name,$params=[]){
        $form->addReCaptcha($name, NULL, $params["message"]);
        return $form;
    }
    
    /**
     * @param \Nette\Application\UI\Form $form
     * @param string $name
     * @param array $params
     * @return \Nette\Application\UI\Form
     */
    protected function addCheckBox(\Nette\Application\UI\Form $form,$name,$params=[]){
        isset($params["title"]) ? false : $params["title"]=$name;
        $element = $form->addCheckbox($name, $params["title"]);
        
        isset($params["defaultValue"]) ? $element->setDefaultValue($params["defaultValue"]) : false;
        (isset($params["disabled"]) and $params["disabled"]==1) ? $element->setDisabled(): false;
        isset($this->values[$name]) ? $element->setValue($this->values[$name]) : false;
        
        if(isset($params["isRequired"]) and $params["isRequired"]==1){
            $this->translator == null ? $element->setRequired("Element " . $params["title"] . " must be set") : $element->setRequired($this->translator->translate("base.controls.form.emptySelectionWarning",["element"=>$this->translator->translate($params["title"])]));
        }
       
        return $form;        
    }
    
    /**
     * @param \Nette\Application\UI\Form $form
     * @param string $name
     * @param array $params
     * @return \Nette\Application\UI\Form
     */
    protected function addCheckBoxList(\Nette\Application\UI\Form $form,$name,$params=[]){
        isset($params["title"]) ? false : $params["title"]=$name;
        isset($this->list[$name]) ? false : $this->list[$name]=[];        
        (isset($params["items"]) and count($params["items"])>0) ? $this->list[$name]=$params["items"] : false;
        
        $element = $form->addCheckboxList($name, $params["title"],$this->list[$name]);

        isset($params["defaultValue"]) ? $element->setDefaultValue($params["defaultValue"]) : false;
        (isset($params["disabled"]) and $params["disabled"]==1) ? $element->setDisabled() : false;
        isset($this->values[$name]) ? $element->setValue($this->values[$name]) : false;
        
        if(isset($params["isRequired"]) and $params["isRequired"]==1){
            $this->translator==null ? $element->setRequired("Element " . $params["title"] . " must be set") : $element->setRequired($this->translator->translate("base.controls.form.emptySelectionWarning",["element"=>$this->translator->translate($params["title"])]));
        }
        return $form; 
    }
    
    /**
     * @param \Nette\Application\UI\Form $form
     * @param string $name
     * @param array $params
     * @return \Nette\Application\UI\Form
     */
    protected function addRadiolist(\Nette\Application\UI\Form $form,$name,$params=[]){
        isset($params["title"]) ? false : $params["title"]=$name;
        isset($this->list[$name]) ? false : $this->list[$name]=[]; 
        (isset($params["items"]) and count($params["items"])>0) ? $this->list[$name]=$params["items"] : false;

        $element = $form->addRadioList($name, $params["title"],$this->list[$name]);
        
        isset($params["defaultValue"]) ? $element->setDefaultValue($params["defaultValue"]) : false;
        (isset($params["disabled"]) and $params["disabled"]==1) ? $element->setDisabled() : false;
        isset($this->values[$name]) ? $element->setValue($this->values[$name]) : false;
        
        if(isset($params["isRequired"]) and $params["isRequired"]==1){
            $this->translator==null ? $element->setRequired("Element " . $params["title"] . " must be set") : $element->setRequired($this->translator->translate("base.controls.form.emptySelectionWarning",["element"=>$this->translator->translate($params["title"])]));
        }
        return $form; 
    }
    
    /**
     * @param \Nette\Application\UI\Form $form
     * @param string $name
     * @param array $params
     * @return \Nette\Application\UI\Form
     */
    protected function addSelect(\Nette\Application\UI\Form $form,$name,$params=[]){
        isset($params["title"]) ? false : $params["title"]=$name;
        isset($this->list[$name]) ? false : $this->list[$name]=[]; 
        (isset($params["items"]) and count($params["items"])>0) ? $this->list[$name]=$params["items"] : false;

        $element = $form->addSelect($name, $params["title"],$this->list[$name]);
        
        isset($params["defaultValue"]) ? $element->setDefaultValue($params["defaultValue"]) : false;
        (isset($params["disabled"]) and $params["disabled"]==1) ? $element->setDisabled() : false;
        isset($this->values[$name]) ? $element->setValue($this->values[$name]) : false;
        
        if(isset($params["isRequired"]) and $params["isRequired"]==1){
            $this->translator==null ? $element->setRequired("Element " . $params["title"] . " must be set") : $element->setRequired($this->translator->translate("base.controls.form.emptySelectionWarning",["element"=>$this->translator->translate($params["title"])]));
        }
        return $form; 
    }
    
    /**
     * @param \Nette\Application\UI\Form $form
     * @param string $name
     * @param array $params
     * @return \Nette\Application\UI\Form
     */
    protected function addMultiselect(\Nette\Application\UI\Form $form,$name,$params=[]){
        isset($params["title"]) ? false : $params["title"]=$name;
        isset($this->list[$name]) ? false : $this->list[$name]=[]; 
        (isset($params["items"]) and count($params["items"])>0) ? $this->list[$name]=$params["items"] : false;

        $element = $form->addMultiSelect($name, $params["title"],$this->list[$name]);

        isset($params["defaultValue"]) ? $element->setDefaultValue($params["defaultValue"]) : false;
        (isset($params["disabled"]) and $params["disabled"]==1) ? $element->setDisabled() : false;
        isset($this->values[$name]) ? $element->setValue($this->values[$name]) : false;
        
        if(isset($params["isRequired"]) and $params["isRequired"]==1){
            $this->translator==null ? $element->setRequired("Element " . $params["title"] . " must be set") : $element->setRequired($this->translator->translate("base.controls.form.emptySelectionWarning",["element"=>$this->translator->translate($params["title"])]));
        }
        return $form; 
    }
    
    /**
     * @param \Nette\Application\UI\Form $form
     * @param string $name
     * @param array $params
     * @return \Nette\Application\UI\Form
     */
    protected function addUpload(\Nette\Application\UI\Form $form,$name,$params=[]){
        isset($params["title"]) ? false : $params["title"]=$name;
        
        $element = $form->addUpload($name, $params["title"]);
        
        if(isset($params["isRequired"]) and $params["isRequired"]==1){
            $this->translator==null ? $element->setRequired("Element " . $params["title"] . " must be set") : $element->setRequired($this->translator->translate("base.controls.form.emptySelectionWarning",["element"=>$this->translator->translate($params["title"])]));
        }
        (isset($params["disabled"]) and $params["disabled"]==1) ? $element->setDisabled() : false;
        
        if(isset($params["rules"]) and count($params["rules"])>0){
            foreach ($params["rules"] as $rule){
                isset($rule["patterns"]) ? false : $rule["patterns"]=null;
                $this->addRule($element, $rule);
            }
        }
        return $form;
    }
    
    /**
     * @param \Nette\Application\UI\Form $form
     * @param string $name
     * @param array $params
     * @return \Nette\Application\UI\Form
     */
    protected function addMultiupload(\Nette\Application\UI\Form $form,$name,$params=[]){
        isset($params["title"]) ? false : $params["title"]=$name;
        
        $element = $form->addMultiUpload($name, $params["title"]);

        if(isset($params["isRequired"]) and $params["isRequired"]==1){
            $this->translator==null ? $element->setRequired("Element " . $params["title"] . " must be set") : $element->setRequired($this->translator->translate("base.controls.form.emptySelectionWarning",["element"=>$this->translator->translate($params["title"])]));
        }
        
        (isset($params["disabled"]) and $params["disabled"]==1) ? $element->setDisabled() : false;
        
        if(isset($params["rules"]) and count($params["rules"])>0){
            foreach ($params["rules"] as $rule){
                isset($rule["patterns"]) ? false : $rule["patterns"]=null;
                $this->addRule($element, $rule);
            }
        }
        return $form;
    }
    
    /**
     * @param \Nette\Application\UI\Form $form
     * @param string $name
     * @param array $params
     * @param mixed $value
     * @return \Nette\Application\UI\Form
     */
    protected function addHidden(\Nette\Application\UI\Form $form,$name,$params=[],$value=null){
        if($value==null){
            isset($this->values[$name]) ? $form->addHidden($name,$this->values[$name]) : $form->addHidden($name,0);
        }
        else{
            $form->addHidden($name,$value);
        }
        return $form;
    }
}