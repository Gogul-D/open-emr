<?php
/* Smarty version 4.3.4, created on 2025-04-21 11:38:09
  from '/var/www/openemr_tb/gacl/admin/templates/phpgacl/acl_admin.tpl' */

/* @var Smarty_Internal_Template $_smarty_tpl */
if ($_smarty_tpl->_decodeProperties($_smarty_tpl, array (
  'version' => '4.3.4',
  'unifunc' => 'content_68068281300967_71693688',
  'has_nocache_code' => false,
  'file_dependency' => 
  array (
    '3e4b2a72dc4e6ddb39e73290b38393034bffb03d' => 
    array (
      0 => '/var/www/openemr_tb/gacl/admin/templates/phpgacl/acl_admin.tpl',
      1 => 1700108884,
      2 => 'file',
    ),
  ),
  'includes' => 
  array (
    'file:phpgacl/header.tpl' => 1,
    'file:phpgacl/acl_admin_js.tpl' => 1,
    'file:phpgacl/navigation.tpl' => 1,
    'file:phpgacl/footer.tpl' => 1,
  ),
),false)) {
function content_68068281300967_71693688 (Smarty_Internal_Template $_smarty_tpl) {
$_smarty_tpl->_checkPlugins(array(0=>array('file'=>'/var/www/openemr_tb/vendor/smarty/smarty/libs/plugins/function.html_options.php','function'=>'smarty_function_html_options',),));
$_smarty_tpl->_subTemplateRender("file:phpgacl/header.tpl", $_smarty_tpl->cache_id, $_smarty_tpl->compile_id, 0, $_smarty_tpl->cache_lifetime, array(), 0, false);
if ((isset($_smarty_tpl->tpl_vars['js_array']->value))) {?>
    <?php echo '<script'; ?>
><?php echo $_smarty_tpl->tpl_vars['js_array']->value;
echo '</script'; ?>
>
<?php }
$_smarty_tpl->_subTemplateRender("file:phpgacl/acl_admin_js.tpl", $_smarty_tpl->cache_id, $_smarty_tpl->compile_id, 0, $_smarty_tpl->cache_lifetime, array(), 0, false);
?>
  </head>
<body onload="populate(document.acl_admin.aco_section,document.acl_admin.elements['aco[]'], '<?php if ((isset($_smarty_tpl->tpl_vars['js_aco_array_name']->value))) {
echo $_smarty_tpl->tpl_vars['js_aco_array_name']->value;
}?>');populate(document.acl_admin.aro_section,document.acl_admin.elements['aro[]'], '<?php if ((isset($_smarty_tpl->tpl_vars['js_aro_array_name']->value))) {
echo $_smarty_tpl->tpl_vars['js_aro_array_name']->value;
}?>')">
<?php $_smarty_tpl->_subTemplateRender("file:phpgacl/navigation.tpl", $_smarty_tpl->cache_id, $_smarty_tpl->compile_id, 0, $_smarty_tpl->cache_lifetime, array(), 0, false);
?>
  <form method="post" name="acl_admin" action="acl_admin.php" onsubmit="select_all(document.acl_admin.elements['selected_aco[]']);select_all(document.acl_admin.elements['selected_aro[]']);select_all(document.acl_admin.elements['selected_aro[]']);return true;">
    <input type="hidden" name="csrf_token_form" value="<?php echo attr($_smarty_tpl->tpl_vars['CSRF_TOKEN_FORM']->value);?>
">
    <div align="center">
      <table cellpadding="2" cellspacing="2" border="2" align="center">
        <tbody>
          <tr>
            <th width="24%">Sections</th>
            <th width="24%">Access Control Objects</th>
            <th width="4%">&nbsp;</th>
            <th width="24%">Selected</th>
            <th width="24%">Access</th>
          </tr>
          <tr valign="top" align="center">
            <td>
              [ <a href="edit_object_sections.php?object_type=aco&return_page=<?php if ((isset($_smarty_tpl->tpl_vars['return_page']->value))) {
echo attr_url($_smarty_tpl->tpl_vars['return_page']->value);
}?>">Edit</a> ]
              <br />
              <select name="aco_section" tabindex="0" size="10" onclick="populate(document.acl_admin.aco_section,document.acl_admin.elements['aco[]'], '<?php if ((isset($_smarty_tpl->tpl_vars['js_aco_array_name']->value))) {
echo $_smarty_tpl->tpl_vars['js_aco_array_name']->value;
}?>')">
                  <?php if ((isset($_smarty_tpl->tpl_vars['options_aco_sections']->value))) {?>
                      <?php if ((isset($_smarty_tpl->tpl_vars['aco_section_value']->value))) {?>
                          <?php echo smarty_function_html_options(array('options'=>$_smarty_tpl->tpl_vars['options_aco_sections']->value,'selected'=>$_smarty_tpl->tpl_vars['aco_section_value']->value),$_smarty_tpl);?>

                      <?php } else { ?>
                          <?php echo smarty_function_html_options(array('options'=>$_smarty_tpl->tpl_vars['options_aco_sections']->value),$_smarty_tpl);?>

                      <?php }?>
                  <?php }?>
              </select>
            </td>
            <td>
              [ <a href="javascript: location.href = 'edit_objects.php?object_type=aco&section_value=' + document.acl_admin.aco_section.options[document.acl_admin.aco_section.selectedIndex].value + '&return_page=<?php if ((isset($_smarty_tpl->tpl_vars['return_page']->value))) {
echo attr_url($_smarty_tpl->tpl_vars['return_page']->value);
}?>';">Edit</a> ]
              <br />
              <select name="aco[]" tabindex="0" size="10" width="200" multiple>
              </select>
            </td>
            <td valign="middle">
              <br /><input type="button" class="select" name="select" value="&nbsp;&gt;&gt;&nbsp;" onClick="select_item(document.acl_admin.aco_section, document.acl_admin.elements['aco[]'], document.acl_admin.elements['selected_aco[]'])">
              <br /><input type="button" class="deselect" name="deselect" value="&nbsp;&lt;&lt;&nbsp;" onClick="deselect_item(document.acl_admin.elements['selected_aco[]'])">
            </td>
            <td>
              <br />
              <select name="selected_aco[]" tabindex="0" size="10" multiple>
                <?php if ((isset($_smarty_tpl->tpl_vars['options_selected_aco']->value))) {?>
				  <?php echo smarty_function_html_options(array('options'=>$_smarty_tpl->tpl_vars['options_selected_aco']->value,'selected'=>$_smarty_tpl->tpl_vars['selected_aco']->value),$_smarty_tpl);?>

                <?php }?>
              </select>
            </td>
            <td valign="middle">
              <table class="invisible">
                <tr align="left"><td><input type="radio" class="radio" name="allow" value="1" <?php if ((isset($_smarty_tpl->tpl_vars['allow']->value)) && $_smarty_tpl->tpl_vars['allow']->value == 1) {?>checked<?php }?> /></td><td>Allow</td></tr>
                <tr align="left"><td><input type="radio" class="radio" name="allow" value="0" <?php if ((isset($_smarty_tpl->tpl_vars['allow']->value)) && $_smarty_tpl->tpl_vars['allow']->value == 0) {?>checked<?php }?> /></td><td>Deny</td></tr>
              	<tr class="spacer"><td colspan="2"></td></tr>
              	<tr align="left"><td><input type="checkbox" class="checkbox" name="enabled" value="1" <?php if ((isset($_smarty_tpl->tpl_vars['enabled']->value)) && $_smarty_tpl->tpl_vars['enabled']->value == 1) {?>checked<?php }?> /></td><td>Enabled</td></tr>
             </table>
           </td>
          </tr>

          <tr>
            <th>Sections</th>
            <th>Access Request Objects</th>
            <th>&nbsp;</th>
            <th>Selected</th>
            <th>Groups</th>
          </tr>
          <tr valign="top" align="center">
            <td>
              [ <a href="edit_object_sections.php?object_type=aro&return_page=<?php if ((isset($_smarty_tpl->tpl_vars['return_page']->value))) {
echo attr_url($_smarty_tpl->tpl_vars['return_page']->value);
}?>">Edit</a> ]
              <br />
              <select name="aro_section" tabindex="0" size="10" onclick="populate(document.acl_admin.aro_section,document.acl_admin.elements['aro[]'],'<?php if ((isset($_smarty_tpl->tpl_vars['js_aro_array_name']->value))) {
echo $_smarty_tpl->tpl_vars['js_aro_array_name']->value;
}?>')">
                  <?php if ((isset($_smarty_tpl->tpl_vars['options_aro_sections']->value))) {?>
                      <?php echo smarty_function_html_options(array('options'=>$_smarty_tpl->tpl_vars['options_aro_sections']->value,'selected'=>$_smarty_tpl->tpl_vars['aro_section_value']->value),$_smarty_tpl);?>

                  <?php }?>
              </select>
            </td>
            <td>
              [ <a href="javascript: location.href = 'edit_objects.php?object_type=aro&section_value=' + document.acl_admin.aro_section.options[document.acl_admin.aro_section.selectedIndex].value + '&return_page=<?php if ((isset($_smarty_tpl->tpl_vars['return_page']->value))) {
echo attr_url($_smarty_tpl->tpl_vars['return_page']->value);
}?>';">Edit</a> ]
              [ <a href="#" onClick="window.open('object_search.php?src_form=acl_admin&object_type=aro&section_value=' + document.acl_admin.aro_section.options[document.acl_admin.aro_section.selectedIndex].value + '&return_page=<?php if ((isset($_smarty_tpl->tpl_vars['return_page']->value))) {
echo attr($_smarty_tpl->tpl_vars['return_page']->value);
}?>','','status=yes,width=400,height=400');return false;">Search</a> ]
              <br />
              <select name="aro[]" tabindex="0" size="10" width="200" multiple>
              </select>
            </td>
            <td valign="middle">
              <br /><input type="button" class="select" name="select" value="&nbsp;&gt;&gt;&nbsp;" onClick="select_item(document.acl_admin.aro_section, document.acl_admin.elements['aro[]'], document.acl_admin.elements['selected_aro[]'])">
              <br /><input type="button" class="deselect" name="deselect" value="&nbsp;&lt;&lt;&nbsp;" onClick="deselect_item(document.acl_admin.elements['selected_aro[]'])">
            </td>
            <td>
             <br />
             <select name="selected_aro[]" tabindex="0" size="10" multiple>
               <?php if ((isset($_smarty_tpl->tpl_vars['options_selected_aro']->value))) {?>
			       <?php echo smarty_function_html_options(array('options'=>$_smarty_tpl->tpl_vars['options_selected_aro']->value,'selected'=>$_smarty_tpl->tpl_vars['selected_aro']->value),$_smarty_tpl);?>

               <?php }?>
             </select>
            </td>
            <td>
              [ <a href="group_admin.php?group_type=aro&return_page=<?php echo attr_url($_smarty_tpl->tpl_vars['SCRIPT_NAME']->value);?>
?action=<?php echo attr_url($_smarty_tpl->tpl_vars['action']->value);?>
&acl_id=<?php if ((isset($_smarty_tpl->tpl_vars['acl_id']->value))) {
echo attr_url($_smarty_tpl->tpl_vars['acl_id']->value);
}?>">Edit</a> ]
              <br />
			  <select name="aro_groups[]" tabindex="0" size="8" multiple>
                  <?php if ((isset($_smarty_tpl->tpl_vars['options_aro_groups']->value))) {?>
			          <?php echo smarty_function_html_options(array('options'=>$_smarty_tpl->tpl_vars['options_aro_groups']->value,'selected'=>$_smarty_tpl->tpl_vars['selected_aro_groups']->value),$_smarty_tpl);?>

                  <?php }?>
			  </select>
			  <br /><input type="button" class="un-select" name="Un-Select" value="Un-Select" onClick="unselect_all(document.acl_admin.elements['aro_groups[]'])">
            </td>
          </tr>

          <tr>
            <th colspan="5">
              [ <a href="javascript: showObject('axo_row1');showObject('axo_row2');setCookie('show_axo',1);">Show</a> / <a href="javascript: hideObject('axo_row1');hideObject('axo_row2');deleteCookie('show_axo');">Hide</a> ] Access eXtension Objects (Optional)
            </th>
          </tr>

          <tr id="axo_row1" <?php if (!(isset($_smarty_tpl->tpl_vars['show_axo']->value)) || !$_smarty_tpl->tpl_vars['show_axo']->value) {?>class="hide"<?php }?>>
            <th>Sections</th>
            <th>Access eXtension Objects</th>
            <th>&nbsp;</th>
            <th>Selected</th>
            <th>Groups</th>
          </tr>
          <tr valign="top" align="center" id="axo_row2" <?php if (!(isset($_smarty_tpl->tpl_vars['show_axo']->value)) || !$_smarty_tpl->tpl_vars['show_axo']->value) {?>class="hide"<?php }?>>
            <td>
              [ <a href="edit_object_sections.php?object_type=axo&return_page=<?php if ((isset($_smarty_tpl->tpl_vars['return_page']->value))) {
echo attr_url($_smarty_tpl->tpl_vars['return_page']->value);
}?>">Edit</a> ]
              <br />
              <select name="axo_section" tabindex="0" size="10" onclick="populate(document.acl_admin.axo_section,document.acl_admin.elements['axo[]'],'<?php if ((isset($_smarty_tpl->tpl_vars['js_axo_array_name']->value))) {
echo $_smarty_tpl->tpl_vars['js_axo_array_name']->value;
}?>')">
                  <?php if ((isset($_smarty_tpl->tpl_vars['options_axo_sections']->value))) {?>
                      <?php echo smarty_function_html_options(array('options'=>$_smarty_tpl->tpl_vars['options_axo_sections']->value,'selected'=>$_smarty_tpl->tpl_vars['axo_section_value']->value),$_smarty_tpl);?>

                  <?php }?>
              </select>
            </td>
            <td>
              [ <a href="javascript: location.href = 'edit_objects.php?object_type=axo&section_value=' + document.acl_admin.axo_section.options[document.acl_admin.axo_section.selectedIndex].value + '&return_page=<?php if ((isset($_smarty_tpl->tpl_vars['return_page']->value))) {
echo attr_url($_smarty_tpl->tpl_vars['return_page']->value);
}?>';">Edit</a> ]
              [ <a href="#" onClick="window.open('object_search.php?src_form=acl_admin&object_type=axo&section_value=' + document.acl_admin.axo_section.options[document.acl_admin.axo_section.selectedIndex].value + '&return_page=<?php if ((isset($_smarty_tpl->tpl_vars['return_page']->value))) {
echo attr($_smarty_tpl->tpl_vars['return_page']->value);
}?>','','status=yes,width=400,height=400');return false;">Search</a> ]
              <br />
              <select name="axo[]" tabindex="0" size="10" width="200" multiple>
              </select>
            </td>
            <td valign="middle">
              <br /><input type="button" class="select" name="select" value="&nbsp;&gt;&gt;&nbsp;" onClick="select_item(document.acl_admin.axo_section, document.acl_admin.elements['axo[]'], document.acl_admin.elements['selected_axo[]'])">
              <br /><input type="button" class="deselect" name="deselect" value="&nbsp;&lt;&lt;&nbsp;" onClick="deselect_item(document.acl_admin.elements['selected_axo[]'])">
            </td>
            <td>
              <br />
              <select name="selected_axo[]" tabindex="0" size="10" multiple>
                <?php if ((isset($_smarty_tpl->tpl_vars['options_selected_axo']->value))) {?>
                    <?php echo smarty_function_html_options(array('options'=>$_smarty_tpl->tpl_vars['options_selected_axo']->value,'selected'=>$_smarty_tpl->tpl_vars['selected_axo']->value),$_smarty_tpl);?>

                <?php }?>
              </select>
            </td>
            <td>
              [ <a href="group_admin.php?group_type=axo&return_page=<?php echo attr_url($_smarty_tpl->tpl_vars['SCRIPT_NAME']->value);?>
?action=<?php echo attr_url($_smarty_tpl->tpl_vars['action']->value);?>
&acl_id=<?php if ((isset($_smarty_tpl->tpl_vars['acl_id']->value))) {
echo attr_url($_smarty_tpl->tpl_vars['acl_id']->value);
}?>">Edit</a> ]
              <br />
              <select name="axo_groups[]" tabindex="0" size="8" multiple>
                  <?php if ((isset($_smarty_tpl->tpl_vars['options_axo_groups']->value))) {?>
                      <?php echo smarty_function_html_options(array('options'=>$_smarty_tpl->tpl_vars['options_axo_groups']->value,'selected'=>$_smarty_tpl->tpl_vars['selected_axo_groups']->value),$_smarty_tpl);?>

                  <?php }?>
              </select>
              <br /><input type="button" class="un-select" name="Un-Select" value="Un-Select" onClick="unselect_all(document.acl_admin.elements['axo_groups[]'])">
            </td>
        </tr>

        <tr>
			<th colspan="5">Miscellaneous Attributes</th>
		</tr>
        <tr valign="top" align="left">
			<td align="center">
                <b>ACL Section</b>
            </td>
			<td>
                <b>Extended Return Value:</b>
            </td>
            <td colspan="4">
                <input type="text" name="return_value" size="50" value="<?php if ((isset($_smarty_tpl->tpl_vars['return_value']->value))) {
echo attr($_smarty_tpl->tpl_vars['return_value']->value);
}?>" id="return_value">
            </td>
		</tr>
		<tr valign="top" align="left">
			<td align="center">
			[ <a href="edit_object_sections.php?object_type=acl&return_page=<?php if ((isset($_smarty_tpl->tpl_vars['return_page']->value))) {
echo attr_url($_smarty_tpl->tpl_vars['return_page']->value);
}?>">Edit</a> ]
			<br />
			<select name="acl_section" tabindex="0" size="3">
                <?php if ((isset($_smarty_tpl->tpl_vars['options_acl_sections']->value))) {?>
                    <?php if ((isset($_smarty_tpl->tpl_vars['acl_section_value']->value))) {?>
			            <?php echo smarty_function_html_options(array('options'=>$_smarty_tpl->tpl_vars['options_acl_sections']->value,'selected'=>$_smarty_tpl->tpl_vars['acl_section_value']->value),$_smarty_tpl);?>

                    <?php } else { ?>
                        <?php echo smarty_function_html_options(array('options'=>$_smarty_tpl->tpl_vars['options_acl_sections']->value),$_smarty_tpl);?>

                    <?php }?>
                <?php }?>
			</select>
		  </td>
          <td><b>Note:</b></td>
          <td colspan="4"><textarea name="note" rows="4" cols="40"><?php if ((isset($_smarty_tpl->tpl_vars['note']->value))) {
echo text($_smarty_tpl->tpl_vars['note']->value);
}?></textarea></td>
		</tr>
        <tr class="controls" align="center">
          <td colspan="5">
            <input type="submit" class="button" name="action" value="Submit"> <input type="reset" class="button" value="Reset">
          </td>
        </tr>
      </tbody>
    </table>
	<input type="hidden" name="acl_id" value="<?php if ((isset($_smarty_tpl->tpl_vars['acl_id']->value))) {
echo attr($_smarty_tpl->tpl_vars['acl_id']->value);
}?>">
	<input type="hidden" name="return_page" value="<?php if ((isset($_smarty_tpl->tpl_vars['return_page']->value))) {
echo attr($_smarty_tpl->tpl_vars['return_page']->value);
}?>">
  </div>
</form>
<?php $_smarty_tpl->_subTemplateRender("file:phpgacl/footer.tpl", $_smarty_tpl->cache_id, $_smarty_tpl->compile_id, 0, $_smarty_tpl->cache_lifetime, array(), 0, false);
}
}
