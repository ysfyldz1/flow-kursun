<?php
// Outlook eklentisi manifest'i (XML, MailApp 1.1). Kuruma özel: adresler bu kurulumun alan adından.
// Okuma ekranında "Flow'a kaydet", yazma ekranında "Gönderince Flow'a kaydet" düğmesi; ikisi de aynı paneli açar.
$x = fn (string $s) => htmlspecialchars($s, ENT_XML1 | ENT_QUOTES, 'UTF-8');
$name = 'Flow' . ($org !== '' ? ' · ' . mb_substr($org, 0, 100) : '');
$pane = $base . '/outlook/pane';
echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
?>
<OfficeApp xmlns="http://schemas.microsoft.com/office/appforoffice/1.1"
           xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
           xmlns:bt="http://schemas.microsoft.com/office/officeappbasictypes/1.0"
           xmlns:mailappor="http://schemas.microsoft.com/office/mailappversionoverrides/1.0"
           xsi:type="MailApp">
  <Id><?= $x($id) ?></Id>
  <Version><?= $x(preg_match('/^\d+\.\d+\.\d+$/', $version) ? $version . '.0' : '1.0.0.0') ?></Version>
  <ProviderName><?= $x($org !== '' ? mb_substr($org, 0, 100) : 'Flow') ?></ProviderName>
  <DefaultLocale>tr-TR</DefaultLocale>
  <DisplayName DefaultValue="<?= $x(mb_substr($name, 0, 125)) ?>"/>
  <Description DefaultValue="E-postayı Flow'da bir projeye kaydeder; isterseniz zaman kaydı da girer.">
    <Override Locale="en-US" Value="Saves the email to a project in Flow, optionally with a time entry."/>
  </Description>
  <IconUrl DefaultValue="<?= $x($base) ?>/outlook/icon-64.png"/>
  <HighResolutionIconUrl DefaultValue="<?= $x($base) ?>/outlook/icon-128.png"/>
  <SupportUrl DefaultValue="<?= $x($base) ?>/"/>
  <AppDomains>
    <AppDomain><?= $x($base) ?></AppDomain>
    <AppDomain>https://login.microsoftonline.com</AppDomain>
  </AppDomains>
  <Hosts>
    <Host Name="Mailbox"/>
  </Hosts>
  <Requirements>
    <Sets>
      <Set Name="Mailbox" MinVersion="1.1"/>
    </Sets>
  </Requirements>
  <FormSettings>
    <Form xsi:type="ItemRead">
      <DesktopSettings>
        <SourceLocation DefaultValue="<?= $x($pane) ?>"/>
        <RequestedHeight>450</RequestedHeight>
      </DesktopSettings>
    </Form>
    <?php // Yazma ekranında da etkinleştiği için (Rule: FormType="Edit") ItemEdit formu zorunlu ?>
    <Form xsi:type="ItemEdit">
      <DesktopSettings>
        <SourceLocation DefaultValue="<?= $x($pane) ?>"/>
      </DesktopSettings>
    </Form>
  </FormSettings>
  <Permissions>ReadWriteItem</Permissions>
  <Rule xsi:type="RuleCollection" Mode="Or">
    <Rule xsi:type="ItemIs" ItemType="Message" FormType="Read"/>
    <Rule xsi:type="ItemIs" ItemType="Message" FormType="Edit"/>
  </Rule>
  <DisableEntityHighlighting>true</DisableEntityHighlighting>
  <VersionOverrides xmlns="http://schemas.microsoft.com/office/mailappversionoverrides" xsi:type="VersionOverridesV1_0">
      <Requirements>
        <bt:Sets DefaultMinVersion="1.3">
          <bt:Set Name="Mailbox"/>
        </bt:Sets>
      </Requirements>
      <Hosts>
        <Host xsi:type="MailHost">
          <DesktopFormFactor>
            <ExtensionPoint xsi:type="MessageReadCommandSurface">
              <OfficeTab id="TabDefault">
                <Group id="flowReadGroup">
                  <Label resid="groupLabel"/>
                  <Control xsi:type="Button" id="flowReadButton">
                    <Label resid="readLabel"/>
                    <Supertip>
                      <Title resid="readLabel"/>
                      <Description resid="readTip"/>
                    </Supertip>
                    <Icon>
                      <bt:Image size="16" resid="icon16"/>
                      <bt:Image size="32" resid="icon32"/>
                      <bt:Image size="80" resid="icon80"/>
                    </Icon>
                    <Action xsi:type="ShowTaskpane">
                      <SourceLocation resid="paneUrl"/>
                    </Action>
                  </Control>
                </Group>
              </OfficeTab>
            </ExtensionPoint>
            <ExtensionPoint xsi:type="MessageComposeCommandSurface">
              <OfficeTab id="TabDefault">
                <Group id="flowComposeGroup">
                  <Label resid="groupLabel"/>
                  <Control xsi:type="Button" id="flowComposeButton">
                    <Label resid="composeLabel"/>
                    <Supertip>
                      <Title resid="composeLabel"/>
                      <Description resid="composeTip"/>
                    </Supertip>
                    <Icon>
                      <bt:Image size="16" resid="icon16"/>
                      <bt:Image size="32" resid="icon32"/>
                      <bt:Image size="80" resid="icon80"/>
                    </Icon>
                    <Action xsi:type="ShowTaskpane">
                      <SourceLocation resid="paneUrl"/>
                    </Action>
                  </Control>
                </Group>
              </OfficeTab>
            </ExtensionPoint>
          </DesktopFormFactor>
        </Host>
      </Hosts>
      <Resources>
        <bt:Images>
          <bt:Image id="icon16" DefaultValue="<?= $x($base) ?>/outlook/icon-16.png"/>
          <bt:Image id="icon32" DefaultValue="<?= $x($base) ?>/outlook/icon-32.png"/>
          <bt:Image id="icon80" DefaultValue="<?= $x($base) ?>/outlook/icon-80.png"/>
        </bt:Images>
        <bt:Urls>
          <bt:Url id="paneUrl" DefaultValue="<?= $x($pane) ?>"/>
        </bt:Urls>
        <bt:ShortStrings>
          <bt:String id="groupLabel" DefaultValue="Flow"/>
          <bt:String id="readLabel" DefaultValue="Flow'a kaydet">
            <bt:Override Locale="en-US" Value="Save to Flow"/>
          </bt:String>
          <bt:String id="composeLabel" DefaultValue="Gönderince Flow'a kaydet">
            <bt:Override Locale="en-US" Value="Save to Flow on send"/>
          </bt:String>
        </bt:ShortStrings>
        <bt:LongStrings>
          <bt:String id="readTip" DefaultValue="Bu e-postayı Flow'da bir projeye kaydeder; isterseniz ekleri belgelere koyar ve zaman kaydı girer.">
            <bt:Override Locale="en-US" Value="Saves this email to a project in Flow; optionally files attachments and adds a time entry."/>
          </bt:String>
          <bt:String id="composeTip" DefaultValue="Proje seçin; e-posta gönderildikten sonra Flow'da o projeye kaydedilir.">
            <bt:Override Locale="en-US" Value="Pick a project; the email is saved to it in Flow after it is sent."/>
          </bt:String>
        </bt:LongStrings>
      </Resources>
  </VersionOverrides>
</OfficeApp>
