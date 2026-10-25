import { usePage } from '@inertiajs/react';
import { Button } from './styles';

// botão flutuante do WhatsApp com a mensagem padrão; sem telefone ou desligado na configuração, não
// aparece (FR-045; spec 021, FR-007)
export default function WhatsAppButton() {
    const { contatos } = usePage().props;

    if (!contatos.whatsapp || !contatos.botao_whatsapp) return null;

    const abrir = () => {
        window.location.href = `https://api.whatsapp.com/send?phone=${contatos.whatsapp}&text=${encodeURIComponent(contatos.mensagem_whatsapp)}`;
    };

    return <Button src="/images/whatsapp.png" onClick={abrir} alt="WhatsApp" />;
}
