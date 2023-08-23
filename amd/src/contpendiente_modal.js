import ModalFactory from 'core/modal_factory';
import Templates from 'core/templates';

export const init = async (activities, selector) => {

    let contpendcard = document.getElementById(selector);
    if (contpendcard) {
        contpendcard.onclick = async function() {
            const modal =  await ModalFactory.create({
                title: 'Contenidos pendientes',
                type: ModalFactory.types.CANCEL,
                body: Templates.render('local_uvirtual/contpendiente_tabla_modal', {activities: activities}),
            });
            modal.setButtonText('cancel', 'Cerrar');
            modal.show();
        };
    }

};