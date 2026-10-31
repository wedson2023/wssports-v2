import { useState } from 'react';
import Swal from 'sweetalert2';

import SaveBar from '../SaveBar';
import SettingsForm from '../SettingsForm';
import api from '../../utils/api';
import { alerta_erro } from '../../utils/alerts';
import { Actions, Button, List, Name, Note, Row, Select, Title, Warning } from './styles';

const campos = [
    { chave: 'telefone_whatsapp_admin', rotulo: 'WhatsApp do Admin', tipo: 'telefone', exemplo: '5582999999999', ajuda: 'Com DDI e DDD, só números. Recebe os avisos da operação.' },
    { chave: 'email_admin', rotulo: 'E-mail do Admin', exemplo: 'admin@suabanca.com', maximo: 150, ajuda: 'Recebe os avisos da operação e o alerta de queda do robô.' },
    { chave: 'aviso_combinacao_whatsapp', rotulo: 'Avisar combinação de risco nova', tipo: 'sim_nao' },
    { chave: 'confirmacao_cadastro', rotulo: 'Exigir confirmação de cadastro', tipo: 'sim_nao', ajuda: 'Com "Sim", o e-mail fica obrigatório e o cliente só entra depois de confirmá-lo. Com "Não", a conta já nasce ativa.' },
];

// avisos ao Admin sem o destino do canal escolhido não saem por esse canal (FR-022)
const destino_faltando = ({ mensagens, telefone_whatsapp_admin, email_admin }) => {
    const do_admin = mensagens.filter((mensagem) => mensagem.destinatario === 'Admin');

    return {
        telefone: !telefone_whatsapp_admin && do_admin.some((mensagem) => mensagem.canal !== 'E-mail'),
        email: !email_admin && do_admin.some((mensagem) => mensagem.canal !== 'WhatsApp'),
    };
};

// seção "Mensagens" da tela Configurações (spec 025, US5, US6 e US8): só o Admin vê. Destinos do Admin,
// canal de cada mensagem, confirmação de cadastro e teste dos canais
export default function MessagesCard({ secao, ao_salvar }) {
    const { valores, erros, quantidade, salvando, mudar, desfazer, autorizacao } = secao;
    const [testando, definir_testando] = useState(null);

    const mudar_canal = (tipo, canal) => mudar('mensagens', valores.mensagens.map((mensagem) => (mensagem.tipo === tipo ? { ...mensagem, canal } : mensagem)));

    const testar = async (canal) => {
        definir_testando(canal);

        try {
            const { data } = await api.post('/configuracoes/mensagens/teste', { canal }, autorizacao);
            Swal.fire({ toast: true, position: 'top-end', icon: 'success', title: data.message, showConfirmButton: false, timer: 2500 });
        } catch (falha) {
            alerta_erro(falha);
        } finally {
            definir_testando(null);
        }
    };

    const faltando = destino_faltando(valores);

    return (
        <>
            <SettingsForm campos={campos} valores={valores} erros={erros} ao_mudar={mudar} />

            {faltando.telefone && (
                <Warning role="status">
                    <i className="material-icons" aria-hidden="true">warning</i>
                    Há avisos ao Admin por WhatsApp sem o telefone preenchido.
                </Warning>
            )}
            {faltando.email && (
                <Warning role="status">
                    <i className="material-icons" aria-hidden="true">warning</i>
                    Há avisos ao Admin por e-mail sem o endereço preenchido.
                </Warning>
            )}

            <Title>Por onde cada mensagem sai</Title>
            <List>
                {valores.mensagens.map((mensagem) => (
                    <Row key={mensagem.tipo}>
                        <Name htmlFor={`canal_${mensagem.tipo}`}>
                            {mensagem.nome}
                            <span>
                                Para: {mensagem.destinatario}
                                {mensagem.reserva ? ' · usa o outro canal se este falhar' : ''}
                            </span>
                        </Name>
                        <Select id={`canal_${mensagem.tipo}`} value={mensagem.canal} onChange={(evento) => mudar_canal(mensagem.tipo, evento.currentTarget.value)}>
                            {valores.canais.map((canal) => <option key={canal} value={canal}>{canal}</option>)}
                        </Select>
                    </Row>
                ))}
            </List>
            <Note>O alerta de queda do robô sai sempre por e-mail, porque o WhatsApp é o canal que caiu.</Note>

            <Title>Testar os canais</Title>
            <Actions>
                <Button type="button" $contorno disabled={Boolean(testando) || quantidade > 0} onClick={() => testar('WhatsApp')}>
                    <i className="material-icons" aria-hidden="true">chat</i>
                    {testando === 'WhatsApp' ? 'Enviando...' : 'Testar WhatsApp'}
                </Button>
                <Button type="button" $contorno disabled={Boolean(testando) || quantidade > 0} onClick={() => testar('E-mail')}>
                    <i className="material-icons" aria-hidden="true">mail</i>
                    {testando === 'E-mail' ? 'Enviando...' : 'Testar e-mail'}
                </Button>
            </Actions>
            {quantidade > 0 && <Note>Salve as alterações para testar.</Note>}

            <SaveBar quantidade={quantidade} salvando={salvando} ao_desfazer={desfazer} ao_salvar={ao_salvar} />
        </>
    );
}
