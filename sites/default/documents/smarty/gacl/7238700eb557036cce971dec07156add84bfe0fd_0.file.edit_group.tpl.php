<?php
/* Smarty version 4.3.4, created on 2025-04-21 11:39:54
  from '/var/www/openemr_tb/gacl/admin/templates/phpgacl/edit_group.tpl' */

/* @var Smarty_Internal_Template $_smarty_tpl */
if ($_smarty_tpl->_decodeProperties($_smarty_tpl, array (
  'version' => '4.3.4',
  'unifunc' => 'content_680682ea10bb79_56563911',
  'has_nocache_code' => false,
  'file_dependency' => 
  array (
    '7238700eb557036cce971dec07156add84bfe0fd' => 
    array (
      0 => '/var/www/openemr_tb/gacl/admin/templates/phpgacl/edit_group.tpl',
      1 => 1700108884,
      2 => 'file',
    ),
  ),
  'includes' => 
  array (
    'file:phpgacl/header.tpl' => 1,
    'file:phpgacl/navigation.tpl' => 1,
    'file:phpgacl/footer.tpl' => 1,
  ),
),false)) {
function content_680682ea10bb79_56563911 (Smarty_Internal_Template $_smarty_tpl) {
$_smarty_tpl->_checkPlugins(array(0=>array('file'=>'/var/www/openemr_tb/vendor/smarty/smarty/libs/plugins/function.html_options.php','function'=>'smarty_function_html_options',),));
$_smarty_tpl->_subTemplateRender("file:phpgacl/header.tpl", $_smarty_tpl->cache_id, $_smarty_tpl->compile_id, 0, $_smarty_tpl->cache_lifetime, array(), 0, false);
?>
    <style type="text/css">

      select {
        margin-top: 0px;
      }
      input.group-name, input.group-value {
        width: 99%;
      }

    </style>
  </head>
  <body>
<?php $_smarty_tpl->_subTemplateRender("file:phpgacl/navigation.tpl", $_smarty_tpl->cache_id, $_smarty_tpl->compile_id, 0, $_smarty_tpl->cache_lifetime, array(), 0, false);
?>
    <form method="post" name="edit_group" action="edit_group.php">
      <input type="hidden" name="csrf_token_form" value="<?php echo attr($_smarty_tpl->tpl_vars['CSRF_TOKEN_FORM']->value);?>
">
      <table cellpadding="2" cellspacing="2" border="2" width="100%">
        <tbody>
          <tr>
            <th width="4%">ID</th>
            <th width="32%">Parent</th>
            <th width="32%">Name</th>
            <th width="32%">Value</th>
          </tr>
          <tr valign="top">
            <td align="center"><?php echo (($tmp = $_smarty_tpl->tpl_vars['id']->value ?? null)===null||$tmp==='' ? "N/A" ?? null : $tmp);?>
</td>
            <td>
                <select name="parent_id" tabindex="0" multiple>
                    <?php if ((isset($_smarty_tpl->tpl_vars['options_groups']->value))) {?>
                        <?php echo smarty_function_html_options(array('options'=>$_smarty_tpl->tpl_vars['options_groups']->value,'selected'=>$_smarty_tpl->tpl_vars['parent_id']->value),$_smarty_tpl);?>

                    <?php }?>
                </select>
            </td>
            <td>
                <input type="text" class="group-name" size="50" name="name" value="<?php if ((isset($_smarty_tpl->tpl_vars['name']->value))) {
echo attr($_smarty_tpl->tpl_vars['name']->value);
}?>">
            </td>
            <td>
                <input type="text" class="group-value" size="50" name="value" value="<?php if ((isset($_smarty_tpl->tpl_vars['value']->value))) {
echo attr($_smarty_tpl->tpl_vars['value']->value);
}?>">
            </td>
          </tr>
          <tr class="controls" align="center">
            <td colspan="4">
              <input type="submit" class="button" name="action" value="Submit"> <input type="reset" class="button" value="Reset">
            </td>
          </tr>
        </tbody>
      </table>
    <input type="hidden" name="group_id" value="<?php if ((isset($_smarty_tpl->tpl_vars['id']->value))) {
echo attr($_smarty_tpl->tpl_vars['id']->value);
}?>">
    <input type="hidden" name="group_type" value="<?php echo attr($_smarty_tpl->tpl_vars['group_type']->value);?>
">
    <input type="hidden" name="return_page" value="<?php echo attr($_smarty_tpl->tpl_vars['return_page']->value);?>
">
  </form>
<?php $_smarty_tpl->_subTemplateRender("file:phpgacl/footer.tpl", $_smarty_tpl->cache_id, $_smarty_tpl->compile_id, 0, $_smarty_tpl->cache_lifetime, array(), 0, false);
}
}
