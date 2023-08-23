import ModalFactory from 'core/modal_factory';
import Templates from 'core/templates';

export const init = async (courses, selector) => {

    let completedcard = document.getElementById(selector);
    if (completedcard) {
        completedcard.onclick = async function() {
            const modal =  await ModalFactory.create({
                title: 'Asignaturas completadas',
                type: ModalFactory.types.CANCEL,
                body: Templates.render('local_uvirtual/completedcourses_modal', {courses: courses}),
            });
            modal.setButtonText('cancel', 'Cerrar');
            modal.show();
        };
    }

};