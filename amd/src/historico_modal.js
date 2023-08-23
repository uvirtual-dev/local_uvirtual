import ModalFactory from 'core/modal_factory';
import Templates from 'core/templates';

export const init = async (courses, selector) => {

    let historicocard = document.getElementById(selector);
    if (historicocard) {
        historicocard.onclick = async function() {
            const modal =  await ModalFactory.create({
                title: 'Histórico académico',
                type: ModalFactory.types.CANCEL,
                body: Templates.render('local_uvirtual/historico_tabla_modal', {courses: courses}),
            });
            modal.setButtonText('cancel', 'Cerrar');
            modal.show();
        };
    }

};