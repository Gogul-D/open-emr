<?php
/* Smarty version 4.3.4, created on 2025-04-11 13:58:21
  from '/var/www/openemr_tb/templates/documents/general_upload.html' */

/* @var Smarty_Internal_Template $_smarty_tpl */
if ($_smarty_tpl->_decodeProperties($_smarty_tpl, array (
  'version' => '4.3.4',
  'unifunc' => 'content_67f9745d2cd015_41838311',
  'has_nocache_code' => false,
  'file_dependency' => 
  array (
    '576af3fe3dba8bcbbe4a65c23aa155be20137cff' => 
    array (
      0 => '/var/www/openemr_tb/templates/documents/general_upload.html',
      1 => 1700108885,
      2 => 'file',
    ),
  ),
  'includes' => 
  array (
  ),
),false)) {
function content_67f9745d2cd015_41838311 (Smarty_Internal_Template $_smarty_tpl) {
$_smarty_tpl->_checkPlugins(array(0=>array('file'=>'/var/www/openemr_tb/library/smarty/plugins/function.xlt.php','function'=>'smarty_function_xlt',),1=>array('file'=>'/var/www/openemr_tb/library/smarty/plugins/function.xla.php','function'=>'smarty_function_xla',),2=>array('file'=>'/var/www/openemr_tb/library/smarty/plugins/function.xl.php','function'=>'smarty_function_xl',),));
?>

<form method="post" enctype="multipart/form-data" action="<?php echo $_smarty_tpl->tpl_vars['FORM_ACTION']->value;?>
" onsubmit="return top.restoreSession()">
    <input type="hidden" name="MAX_FILE_SIZE" value="64000000" />
    <h3><?php if (!empty($_smarty_tpl->tpl_vars['error']->value)) {
echo nl2br((string) text($_smarty_tpl->tpl_vars['error']->value), (bool) 1);
}?></h3>
    <?php if ((!($_smarty_tpl->tpl_vars['patient_id']->value > 0))) {?>
    <div class="text text-danger">
        <?php echo smarty_function_xlt(array('t'=>"IMPORTANT: This upload tool is only for uploading documents on patients that are not yet entered into the system. To upload files for patients whom already have been entered into the system, please use the upload tool linked within the Patient Summary screen."),$_smarty_tpl);?>

        <br/>
        <br/>
    </div>
    <?php }?>
    <div class="text">
        <?php echo smarty_function_xlt(array('t'=>"NOTE: Uploading files with duplicate names will cause the files to be automatically renamed (for example, file.jpg will become file.1.jpg). Filenames are considered unique per patient, not per category."),$_smarty_tpl);?>

        <br/>
        <br/>
    </div>
    <div class="text font-weight-bold">
        <?php echo smarty_function_xlt(array('t'=>"Upload Document"),$_smarty_tpl);?>
 <?php if ($_smarty_tpl->tpl_vars['category_name']->value) {?> <?php echo smarty_function_xlt(array('t'=>"to category"),$_smarty_tpl);?>
 '<?php echo text($_smarty_tpl->tpl_vars['category_name']->value);?>
'<?php }?>
    </div>
    <div class="text">
        <div class="form-group">
            <p>(<small><?php echo smarty_function_xlt(array('t'=>"Multiple files can be uploaded at one time by selecting them using CTRL+Click or SHIFT+Click."),$_smarty_tpl);?>
</small>)</p>
            <span><?php echo smarty_function_xlt(array('t'=>"Source File Path"),$_smarty_tpl);?>
:</span>
            <input type="file" class="form-control-file" name="file[]" id="source-name" multiple="true" />
        </div>
        <div class="form-group">
            <p>(<small><?php echo smarty_function_xlt(array('t'=>"Select below to Zip Directory of image slices."),$_smarty_tpl);?>
</small>)</p>
            <input type="file" class="form-control-file" name="dicom_folder[]" id="dicom_folder" multiple directory=""  webkitdirectory="" moxdirectory="" />
        </div>
        <p>
            <span title="<?php echo smarty_function_xla(array('t'=>'Leave Blank To Keep Original Filename'),$_smarty_tpl);?>
"><?php echo smarty_function_xlt(array('t'=>"Optional Destination or Dicom Study Name"),$_smarty_tpl);?>
:</span>
            <input type="text" class="form-control" name="destination" title="<?php echo smarty_function_xla(array('t'=>'Leave Blank To Keep Original Filename'),$_smarty_tpl);?>
" id="destination-name" />
        </p>
        <?php if (!$_smarty_tpl->tpl_vars['hide_encryption']->value) {?>
        <br />
        <p>
            <span title="<?php echo smarty_function_xla(array('t'=>'Check the box if this is an encrypted file'),$_smarty_tpl);?>
"><?php echo smarty_function_xlt(array('t'=>"Is The File Encrypted?"),$_smarty_tpl);?>
:</span>
            <input type="checkbox" name="encrypted" title="<?php echo smarty_function_xla(array('t'=>'Check the box if this is an encrypted file'),$_smarty_tpl);?>
" id="encrypted" />
        </p>
        <p>
            <span title="<?php echo smarty_function_xla(array('t'=>'Pass phrase to decrypt document'),$_smarty_tpl);?>
"><?php echo smarty_function_xlt(array('t'=>"Pass Phrase"),$_smarty_tpl);?>
:</span>
            <input type="text" class="form-control" name="passphrase" title="<?php echo smarty_function_xla(array('t'=>'Pass phrase to decrypt document'),$_smarty_tpl);?>
" id="passphrase" />
        </p>
        <p><i><?php echo smarty_function_xlt(array('t'=>'Supports AES-256-CBC encryption/decryption only.'),$_smarty_tpl);?>
</i></p>
        <?php }?>
    </div>
    <p>
        <input type="submit" class="btn btn-primary" value="<?php echo smarty_function_xl(array('t'=>attr('Upload')),$_smarty_tpl);?>
" />
    </p>

    <input type="hidden" name="patient_id" value="<?php echo attr($_smarty_tpl->tpl_vars['patient_id']->value);?>
" />
    <input type="hidden" name="category_id" value="<?php echo attr($_smarty_tpl->tpl_vars['category_id']->value);?>
" />
    <input type="hidden" name="process" value="<?php echo attr($_smarty_tpl->tpl_vars['PROCESS']->value);?>
" />
</form>

<br /><br />

<!-- Drag and drop uploader -->
<div id="autouploader">
    <form method="post" enctype="multipart/form-data" action="<?php echo $_smarty_tpl->tpl_vars['GLOBALS']->value['webroot'];?>
/library/ajax/upload.php?patient_id=<?php echo attr_url($_smarty_tpl->tpl_vars['patient_id']->value);?>
&parent_id=<?php echo attr_url($_smarty_tpl->tpl_vars['category_id']->value);?>
&csrf_token_form=<?php echo attr_url($_smarty_tpl->tpl_vars['CSRF_TOKEN_FORM']->value);?>
" class="dropzone">
        <div class="dz-message" data-dz-message><span><?php echo smarty_function_xlt(array('t'=>'Drop files here to upload'),$_smarty_tpl);?>
</span></div>
        <input type="hidden" name="MAX_FILE_SIZE" value="64000000" />
    </form>
</div>

<!-- Section for document template download -->
<form method='post' action='interface/patient_file/download_template.php' onsubmit='return top.restoreSession()'>
    <input type="hidden" name="csrf_token_form" value="<?php echo attr($_smarty_tpl->tpl_vars['CSRF_TOKEN_FORM']->value);?>
" />
    <input type='hidden' name='patient_id' value='<?php echo attr($_smarty_tpl->tpl_vars['patient_id']->value);?>
' />
    <div class='col-sm-6'>
        <p class='text font-weight-bold'><?php echo smarty_function_xlt(array('t'=>"Download document template for this patient and visit"),$_smarty_tpl);?>
</p>
        <div class="input-group">
            <span class="input-group-prepend">
                <button type="submit" class="btn btn-primary btn-download"><?php echo smarty_function_xlt(array('t'=>"Fetch"),$_smarty_tpl);?>
</button>
            </span>
            <select class="form-control" name='form_filename'><?php echo $_smarty_tpl->tpl_vars['TEMPLATES_LIST']->value;?>
</select>
        </div>
    </div>
</form>
<!-- End document template download section -->

<!-- Section for portal document templates -->
<div class='col-sm-6'>
	<p class='text font-weight-bold'><?php echo smarty_function_xlt(array('t'=>"Patient Document Template Forms"),$_smarty_tpl);?>
</p>
	<div class="input-group">
		<span class="input-group-prepend">
			<button class="btn btn-primary btn-download" onclick="callTemplateModule()"><?php echo smarty_function_xlt(array('t'=>"Open"),$_smarty_tpl);?>
</button>
		</span>
		<select class="form-control" id='template_filename'><?php echo $_smarty_tpl->tpl_vars['TEMPLATES_LIST_PATIENT']->value;?>
</select>
	</div>
</div>

<?php if (!empty($_smarty_tpl->tpl_vars['file']->value)) {?>
	<div class="text font-weight-bold">
		<br/>
		<?php echo smarty_function_xlt(array('t'=>'Uploaded'),$_smarty_tpl);?>

	</div>
	<?php
$_from = $_smarty_tpl->smarty->ext->_foreach->init($_smarty_tpl, $_smarty_tpl->tpl_vars['file']->value, 'onefile');
$_smarty_tpl->tpl_vars['onefile']->do_else = true;
if ($_from !== null) foreach ($_from as $_smarty_tpl->tpl_vars['onefile']->value) {
$_smarty_tpl->tpl_vars['onefile']->do_else = false;
?>
		<div class="text">
			<?php if ($_smarty_tpl->tpl_vars['error']->value) {?><i><?php echo nl2br((string) text($_smarty_tpl->tpl_vars['error']->value), (bool) 1);?>
</i><br/><?php }?>
			<?php echo smarty_function_xlt(array('t'=>'Name'),$_smarty_tpl);?>
: <?php echo text($_smarty_tpl->tpl_vars['onefile']->value->get_name());?>
<br /><br />
		</div>
	<?php
}
$_smarty_tpl->smarty->ext->_foreach->restore($_smarty_tpl, 1);
}
}
}
