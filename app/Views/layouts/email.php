<?php
/**
 * Gabarit des e-mails HTML. Les styles sont en ligne : les messageries ignorent les feuilles externes.
 *
 * @var string $sujet
 * @var string $contenu
 * @var string $appUrl
 */
?>
<!doctype html>
<html lang="fr">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($sujet) ?></title>
</head>
<body style="margin:0;padding:0;background:#f2f3f5;font-family:Arial,Helvetica,sans-serif;color:#1d1f23;">
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#f2f3f5;padding:24px 12px;">
    <tr>
        <td align="center">
            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:560px;background:#ffffff;border-radius:10px;overflow:hidden;">
                <tr>
                    <td style="background:#111214;padding:18px 24px;">
                        <span style="font-size:18px;font-weight:bold;letter-spacing:0.04em;color:#ffffff;">PEGASUS</span>
                        <span style="font-size:11px;letter-spacing:0.2em;color:#c42a31;">&nbsp;INSURE</span>
                    </td>
                </tr>
                <tr>
                    <td style="padding:28px 24px 8px;font-size:15px;line-height:1.55;">
                        <?= $contenu ?>
                    </td>
                </tr>
                <tr>
                    <td style="padding:16px 24px 24px;font-size:12px;line-height:1.5;color:#5a606b;border-top:1px solid #e6e8eb;">
                        Message automatique de Pegasus Connect, merci de ne pas y répondre.<br>
                        Pour toute question, utilisez la rubrique Contact de l'application.
                    </td>
                </tr>
            </table>
        </td>
    </tr>
</table>
</body>
</html>
