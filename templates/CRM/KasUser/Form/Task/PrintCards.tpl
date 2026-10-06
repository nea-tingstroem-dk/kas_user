<div class="crm-block crm-form-block crm-businesscards-form-block">
  <table class="form-layout-compressed">
    <tr>
      <td class="label">{$form.profile.label}</td>
      <td>{$form.profile.html}</td>
    </tr>
    <tr>
      <td class="label">{$form.layout.label}</td>
      <td>{$form.layout.html}</td>
    </tr>
    <tr>
      <td class="label">{$form.copies.label}</td>
      <td>
        {$form.copies.html}
        <div class="description">{ts domain="businesscards"}Use 10 to fill a whole A4 sheet for one person.{/ts}</div>
      </td>
    </tr>
    <tr class="businesscards-skip">
      <td class="label">{$form.skip.label}</td>
      <td>
        {$form.skip.html}
        <div class="description">{ts domain="businesscards"}To reuse a partly used sheet: the number of card positions already used, counted left to right, top to bottom.{/ts}</div>
      </td>
    </tr>
    <tr>
      <td class="label">{$form.logo_source.label}</td>
      <td>
        {$form.logo_source.html}
        <div class="description">{$logoHelp}</div>
      </td>
    </tr>
    <tr>
      <td class="label">{$form.qr_target.label}</td>
      <td>
        {$form.qr_target.html}
        <div class="description">{ts domain="businesscards"}Pick a public Form Builder form or profile, or enter any web address. Profiles must allow anonymous users to create contacts.{/ts}</div>
      </td>
    </tr>
    <tr class="businesscards-title">
      <td class="label">{$form.card_title.label}</td>
      <td>
        {$form.card_title.html}
      </td>
    </tr>
    <tr class="businesscards-qr-url">
      <td class="label">{$form.qr_url.label}</td>
      <td>
        {$form.qr_url.html}
        <div class="description">{ts domain="businesscards" 1=$contactIdToken}Add %1 to include the card holder's contact ID, e.g. to see who referred a new contact.{/ts}</div>
      </td>
    </tr>
    <tr class="businesscards-qr-only">
      <td class="label">{$form.qr_caption.label}</td>
      <td>{$form.qr_caption.html}</td>
    </tr>
    <tr>
      <td class="label">{$form.website.label}</td>
      <td>
        {$form.website.html}
        <div class="description">{ts domain="businesscards"}Shown for contacts who have no website of their own.{/ts}</div>
      </td>
    </tr>
    <tr>
      <td class="label">{$form.show_address.label}</td>
      <td>{$form.show_address.html}</td>
    </tr>
    <tr>
      <td class="label">{$form.accent.label}</td>
      <td>{$form.accent.html}</td>
    </tr>
  </table>

  <div class="crm-submit-buttons">{include file="CRM/common/formButtons.tpl" location="bottom"}</div>
</div>

{literal}
<script type="text/javascript">
  CRM.$(function($) {
    function refresh() {
      var target = $('#qr_target').val();
      $('tr.businesscards-skip').toggle($('#layout').val() === 'a4');
      $('tr.businesscards-qr-url').toggle(target === 'url');
      $('tr.businesscards-qr-only').toggle(target !== 'none');
    }
    $('#layout, #qr_target').on('change', refresh);
    refresh();
  });
</script>
{/literal}
