<?php if (!defined('APPLICATION')) exit(); ?>

<h1><?php echo $this->Data('Title'); ?></h1>
<?php
   echo $this->Form->Open();
   echo $this->Form->Errors();
?>
<div class="Info">
   <?php echo T(''); ?>
</div>
<div class="Settings">
   <ul>
      <li>
         <?php
            echo $this->Form->Label('Category ID for blog discussions', 'CategoryID');
            echo $this->Form->TextBox('CategoryID');
         ?>
      </li>
   </ul>
   <?php echo $this->Form->Button('Save', array('class' => 'Button SliceSubmit')); ?>
</div>   
<?php echo $this->Form->Close(); ?>