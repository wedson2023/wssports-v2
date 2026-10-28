import { formatar_data_hora } from '../../utils/dates';
import { Entry, EntryLine, List, Title } from './styles';

const nomes = (opcoes) => (opcoes.length ? opcoes.map((opcao) => opcao.nome ?? `#${opcao.id}`).join(', ') : '—');

// nomes das vencedoras empatadas de um lado da correção
const empatadas = (opcoes, ids) => nomes(opcoes.filter((opcao) => ids.includes(opcao.id)));

// histórico das correções de resultado na gaveta do especial (spec 024, FR-032, contracts/paginas
// §2): data, usuário, vencedoras de → para, empatadas e divisão quando houver, e as apostas
// reapuradas. Sem correções, não mostra nada
export default function SpecialCorrections({ correcoes }) {
    if (!correcoes?.length) return null;

    return (
        <>
            <Title>Correções de resultado</Title>
            <List>
                {correcoes.map((correcao) => (
                    <Entry key={correcao.id}>
                        <EntryLine>
                            <strong>{formatar_data_hora(correcao.created_at)}</strong>
                            <span>{correcao.usuario ?? '—'}</span>
                        </EntryLine>
                        <EntryLine>
                            <span>{`Vencedoras: ${nomes(correcao.vencedoras_anteriores)} → ${nomes(correcao.vencedoras_novas)}`}</span>
                        </EntryLine>
                        {(correcao.dividir_empate_anterior || correcao.dividir_empate_novo) && (
                            <EntryLine>
                                <span>
                                    {`Empatadas: ${correcao.dividir_empate_anterior ? empatadas(correcao.vencedoras_anteriores, correcao.empatadas_anteriores) : 'sem divisão'}`}
                                    {` → ${correcao.dividir_empate_novo ? empatadas(correcao.vencedoras_novas, correcao.empatadas_novas) : 'sem divisão'}`}
                                </span>
                            </EntryLine>
                        )}
                        <EntryLine>
                            <span>{`${correcao.apostas_afetadas} ${correcao.apostas_afetadas === 1 ? 'aposta reapurada' : 'apostas reapuradas'}`}</span>
                        </EntryLine>
                    </Entry>
                ))}
            </List>
        </>
    );
}
