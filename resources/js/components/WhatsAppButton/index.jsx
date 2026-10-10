import { usePage } from '@inertiajs/react';
import { Button } from './styles';

// botão flutuante do WhatsApp com a mensagem padrão; sem telefone, não aparece (FR-045)
export default function WhatsAppButton() {
    const { contatos } = usePage().props;

    if (!contatos.whatsapp) return null;

    const abrir = () => {
        window.location.href = `https://api.whatsapp.com/send?phone=${contatos.whatsapp}&text=${encodeURIComponent(contatos.mensagem_whatsapp)}`;
    };

    return <Button src="/images/whatsapp.png" onClick={abrir} alt="WhatsApp" />;
}
